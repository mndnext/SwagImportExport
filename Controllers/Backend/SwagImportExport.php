<?php

/**
 * Shopware 4
 * Copyright © shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

/**
 * Shopware ImportExport Plugin
 *
 * @category Shopware
 * @package Shopware\Plugins\SwagImageEditor
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Shopware_Controllers_Backend_SwagImportExport extends Shopware_Controllers_Backend_ExtJs
{
    /**
     * @var Shopware\Models\Category\Category
     */
    protected $profileRepository;

    protected function convertToExtJSTree($node, $isInIteration = false)
    {
        $extjsNode = array();

        if ($node['type'] == 'record') {
            $isIteration = true;

            $extjsNode['iconCls'] = 'sprite-blue-folders-stack';
        } else {
            $isIteration = false;

            if ($isInIteration) {
                $extjsNode['iconCls'] = 'sprite-icon_taskbar_top_inhalte_active';
            }
        }

        if (isset($node['name'])) {
            $extjsNode['text'] = $node['name'];
        }
        if (isset($node['children'])) {
            $extjsNode['expanded'] = true;
            foreach ($node['children'] as $child) {
                $extjsNode['children'][] = $this->convertToExtJSTree($child, $isIteration | $isInIteration);
            }
        }
        if (isset($node['attributes'])) {
            if (!isset($extjsNode['children'])) {
                $extjsNode['expanded'] = true;
                $extjsNode['children'] = array();
            }
            foreach ($node['attributes'] as $attribute) {
                $extjsNode['children'][] = array('text' => $attribute['name'], 'leaf' => true, 'iconCls' => 'sprite-sticky-notes-pin');
            }
        }
        if (!isset($extjsNode['children'])) {
            if ($isInIteration) {
                $extjsNode['leaf'] = true;
            } else {
                $extjsNode['expanded'] = true;
                $extjsNode['children'] = array();
            }
        }

        return $extjsNode;
    }

    public function getProfileAction()
    {
        $postData = array(
            'profileId' => 1,
            'sessionId' => 70,
            'type' => 'export',
            'limit' => array('limit' => 40, 'offset' => 0),
            'max_record_count' => 100,
            'format' => 'xml',
            'adapter' => 'categories',
        );

        $profile = $this->Plugin()->getProfileFactory()->loadProfile($postData);
        $root = $this->convertToExtJSTree(json_decode($profile->getConfig('tree'), 1));

        $this->View()->assign(array('success' => true, 'children' => $root['children']));
    }
    
    /**
     * Returns all profiles into an array
     */
    public function getProfilesAction()
    {
        $profileRepository = $this->getProfileRepository();
        
        $query = $profileRepository->getProfilesListQuery(
            $this->Request()->getParam('filter', array()),
            $this->Request()->getParam('sort', array()),
            $this->Request()->getParam('limit', null),
            $this->Request()->getParam('start')
        )->getQuery();
        
        $count = Shopware()->Models()->getQueryCount($query);

        $data = $query->getArrayResult();
        
        $this->View()->assign(array(
            'success' => true, 'data' => $data, 'total' => $count
        ));
    }
    
    public function prepareAction()
    {
        $postData = array(
            'profileId' => (int) $this->Request()->getParam('profileId'),
            'type' => 'export',
            'format' => $this->Request()->getParam('format')
        );
        
        $profile = $this->Plugin()->getProfileFactory()->loadProfile($postData);
        
        //get profile type
        $postData['adapter'] = $profile->getType();
        
        $dataFactory = $this->Plugin()->getDataFactory();
        $dataIO = $dataFactory->createDataIO($postData);
        
        $ids = $dataIO->preloadRecordIds()->getRecordIds();
        
        $position = $dataIO->getSessionPosition();
        $position = $position == null ? 0 : $position;
        
        $this->View()->assign(array('success' => true, 'position'=> $position, 'count' => count($ids)));
    }
    
    public function exportAction()
    {
        
        $postData = array(
            'profileId' => (int) $this->Request()->getParam('profileId'),
            'type' => 'export',
            'format' => $this->Request()->getParam('format'),
            'sessionId' => $this->Request()->getParam('sessionId'),
        );
        
        $profile = $this->Plugin()->getProfileFactory()->loadProfile($postData);
        
        //get profile type
        $postData['adapter'] = $profile->getType();
        
        //create dataIO
        $dataFactory = $this->Plugin()->getDataFactory();
        $dataIO = $dataFactory->createDataIO($postData);

        // we create the file writer that will write (partially) the result file
        $fileWriter = $this->Plugin()->getFileIOFactory()->createFileWriter($postData);
        
        $dataTransformerChain = $this->Plugin()->getDataTransformerFactory()->createDataTransformerChain(
                $profile, array('isTree' => $fileWriter->hasTreeStructure())
        );
        
        if ($dataIO->getSessionState() == 'new') {
            //todo: create file here ?
            $fileName = $dataIO->generateFileName($profile);
           
            $outputFileName = Shopware()->DocPath() . 'files/import_export/' . $fileName;
            
            // session has no ids stored yet, therefore we must start it and write the file headers
            $header = $dataTransformerChain->composeHeader();
            $fileWriter->writeHeader($outputFileName, $header);
            
            $dataIO->startSession();
        } else {
            $fileName = $dataIO->getDataSession()->getFileName();
            
            $outputFileName = Shopware()->DocPath() . 'files/import_export/' . $fileName;
            
            // session has already loaded ids and some position, so we simply activate it
            $dataIO->resumeSession();
        }
        $dataIO->preloadRecordIds();

        if ($dataIO->getSessionState() == 'active') {

            try {
                // read a bunch of records into simple php array;
                // the count of records may be less than 100 if we are at the end of the read.
                $data = $dataIO->read(10);

                // process that array with the full transformation chain
                $data = $dataTransformerChain->transformForward($data);
                
                // now the array should be a tree and we write it to the file
                $fileWriter->writeRecords($outputFileName, $data);

                // writing is successful, so we write the new position in the session;
                // if if the new position goes above the limits provided by the 
                $dataIO->progressSession(10);
            } catch (Exception $e) {
                return $this->View()->assign(array('success' => false, 'msg'=> $e->getMessage()));
            }
        }
        
        if ($dataIO->getSessionState() == 'finished') {
            // Session finished means we have exported all the ids in the sesssion.
            // Therefore we can close the file with a footer and mark the session as done.
            $footer = $dataTransformerChain->composeFooter();
            $fileWriter->writeFooter($outputFileName, $footer);
            $dataIO->closeSession();
            
            $this->View()->assign(array('success' => true, 'msg'=> 'Export finished successfully'));
        }
        
        $position = $dataIO->getSessionPosition();
        
        $data = $postData;
        $data['position'] =  $position == null ? 0 : $position;
        
        if(!$data['sessionId']){
            $data['sessionId'] = $dataIO->getDataSession()->getId();
        }
                
        return $this->View()->assign(array('success' => true, 'data'=> $data));
    }
    
    /**
     * Helper Method to get access to the category repository.
     *
     * @return Shopware\Models\Category\Repository
     */
    public function getProfileRepository()
    {
        if ($this->profileRepository === null) {
            $this->profileRepository = Shopware()->Models()->getRepository('Shopware\CustomModels\ImportExport\Profile');
        }
        return $this->profileRepository;
    }

    public function Plugin()
    {
        return Shopware()->Plugins()->Backend()->SwagImportExport();
    }

}
