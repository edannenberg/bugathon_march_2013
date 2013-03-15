<?php

Mage::app()->reinitStores();
Mage::app()->setCurrentStore(Mage_Core_Model_Store::ADMIN_CODE);

$numConfigurables = 2;
$numAssociatedSimples = 20;
$disabledModulus = 10;
/** @var $attributeOptions Mage_Eav_Model_Resource_Entity_Attribute_Option_Collection[] */
$attributeOptions = array();
$attributeIds = array();

$installer = new Mage_Catalog_Model_Resource_Setup('catalog_write');
$attribute = new Mage_Catalog_Model_Resource_Eav_Attribute();

// Create attributes with options
for ($iConf = 0; $iConf < $numConfigurables; $iConf++) {
    $attributeCode = 'test_large_configurable' . $iConf;
    $data = array(
        'attribute_code' => $attributeCode,
        'entity_type_id' => $installer->getEntityTypeId('catalog_product'),
        'is_global' => 1,
        'is_user_defined' => 1,
        'frontend_input' => 'select',
        'is_unique' => 0,
        'is_required' => 1,
        'is_configurable' => 1,
        'is_searchable' => 0,
        'is_visible_in_advanced_search' => 0,
        'is_comparable' => 0,
        'is_filterable' => 0,
        'is_filterable_in_search' => 0,
        'is_used_for_promo_rules' => 0,
        'is_html_allowed_on_front' => 1,
        'is_visible_on_front' => 0,
        'used_in_product_listing' => 0,
        'used_for_sort_by' => 0,
        'frontend_label' => array(
            0 => 'Test Large Configurable ' . $iConf
        ),
        'option' => array(
            'value' => array(),
            'order' => array()
        ),
        'backend_type' => 'int',
    );
    for ($iAttr = 0; $iAttr < $numAssociatedSimples; $iAttr++) {
        $data['option']['value']['option_' . $iAttr] = array(0 => 'Option ' . $iConf . '.' . ($iAttr + 1));
        $data['option']['order']['option_' . $iAttr] = $iAttr + 1;
    }
    $attribute->setData($data);
    $attribute->save();
    $installer->addAttributeToSet('catalog_product', 'Default', 'General', $attribute->getId());

    $options = new Mage_Eav_Model_Resource_Entity_Attribute_Option_Collection();
    $options->setAttributeFilter($attribute->getId());
    $attributeOptions[$attributeCode] = $options;

    $attributeIds[$attributeCode] = $attribute->getId();
}

// Create configurables
for ($iConf = 0; $iConf < $numConfigurables; $iConf++) {
    $attributeCode = 'test_large_configurable' . $iConf;
    // Create associated simple products
    for ($iSimple = 0; $iSimple < $numAssociatedSimples; $iSimple++) {
        $attributeOption = $attributeOptions[$attributeCode]->getItemByColumnValue('sort_order', ($iSimple + 1));
        $isDisabled = $iSimple % $disabledModulus;
        $product = new Mage_Catalog_Model_Product();
        $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
                ->setAttributeSetId($installer->getAttributeSetId('catalog_product', 'Default'))
                ->setWebsiteIds(array(1))
                ->setName('Associated Simple ' . $iConf . '.' . $iSimple)
                ->setSku('simple_' . $iConf . '.' . $iSimple)
                ->setPrice(10)
                ->setData($attributeCode, $attributeOption->getId())
                ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
                ->setStatus($isDisabled ? Mage_Catalog_Model_Product_Status::STATUS_DISABLED : Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setStockData(
                    array(
                        'use_config_manage_stock' => 1,
                        'qty' => 100,
                        'is_qty_decimal' => 0,
                        'is_in_stock' => 1,
                    )
                )
                ->save();
        $dataOption = array(
            'label' => 'test',
            'attribute_id' => $attributeOption->getAttributeId(),
            'value_index' => $attributeOption->getId(),
            'is_percent' => false,
            'pricing_value' => 5,
        );
        $productsData[$product->getId()] = array($dataOption);
        $attributeValues[] = $dataOption;
    }
    $product = new Mage_Catalog_Model_Product();
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
            ->setAttributeSetId($installer->getAttributeSetId('catalog_product', 'Default'))
            ->setWebsiteIds(array(1))
            ->setName('Configurable Product ' . $iConf)
            ->setSku('configurable' . $iConf)
            ->setPrice(100)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setStockData(
                array(
                    'use_config_manage_stock' => 1,
                    'is_in_stock' => 1,
                )
            )
            ->setConfigurableProductsData($productsData)
            ->setConfigurableAttributesData(
                array(
                    array(
                        'attribute_id' => $attributeIds[$attributeCode],
                        'attribute_code' => $attributeCode,
                        'frontend_label' => 'test',
                        'values' => $attributeValues,
                    )
                )
            )
            ->save();
}