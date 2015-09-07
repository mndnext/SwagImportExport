<?php

namespace Shopware\Components\SwagImportExport\Validators\Articles;

use Shopware\Components\SwagImportExport\Validators\Validator;
use Shopware\Components\SwagImportExport\Utils\SnippetsHelper;
use Shopware\Components\SwagImportExport\Exception\AdapterException;

class ArticleValidator extends Validator
{
    public static $mapper = array(
        'string' => array( //TODO: maybe we don't need to check fields which contains string?
            'orderNumber',
            'mainNumber',
            'name',
            'additionalText',
            'supplierName',
            'description',
            'descriptionLong',
            'shippingTime',
            'metaTitle',
            'keywords',
            'packUnit',
            'supplierNumber',
            'ean',
        ),
        'float' => array(
            'tax',
            'purchaseUnit',
            'referenceUnit',
            'weight',
            'width',
            'height',
            'length',
        ),
        'int' => array(
            'active',
            'inStock',
            'stockMin',
            'shippingFree',
            'topSeller',
            'minPurchase',
            'purchaseSteps',
            'maxPurchase',
            'unitId',
            'priceGroupId',
            'priceGroupActive',
            'lastStock',
        ),
        'dateTime' => array(
            'date',
            'changeTime',
            'releaseDate',
        ),
    );

    private $requiredFields = array(
        'orderNumber',
    );

    private $requiredFieldsForCreate = array(
        'name',
        array('supplierName', 'supplierId'),
    );

    private $snippetData = array(
        'orderNumber' => array(
            'adapters/ordernumber_required',
            'Order number is required.'
        ),
        'supplierName' => array(
            'adapters/articles/supplier_not_found',
            'Supplier not found for article %s.'
        ),
        'name' => array(
            'adapters/articles/no_name_provided',
            'Please provide article name for article %s.'
        ),
    );

    /**
     * Checks whether required fields are filled-in
     *
     * @param array $record
     * @throws AdapterException
     */
    public function checkRequiredFields($record)
    {
        foreach ($this->requiredFields as $key) {
            if (isset($record[$key])) {
                continue;
            }

            list($snippetName, $snippetMessage) = $this->snippetData[$key];

            $message = SnippetsHelper::getNamespace()->get($snippetName, $snippetMessage);
            throw new AdapterException($message);
        }
    }

    /**
     * Checks whether required fields for create are filled-in
     *
     * @param array $record
     * @throws AdapterException
     */
    public function checkRequiredFieldsForCreate($record)
    {
        foreach ($this->requiredFieldsForCreate as $key) {
            if (is_array($key)) {
                list($supplierName, $supplierId) = $key;

                if (isset($record[$supplierName]) || isset($record[$supplierId])) {
                    continue;
                }
                $key = $supplierName;
            } elseif (isset($record[$key])) {
                continue;
            }

            list($snippetName, $snippetMessage) = $this->snippetData[$key];

            $message = SnippetsHelper::getNamespace()->get($snippetName, $snippetMessage);
            throw new AdapterException(sprintf($message, $record['orderNumber']));
        }
    }
}