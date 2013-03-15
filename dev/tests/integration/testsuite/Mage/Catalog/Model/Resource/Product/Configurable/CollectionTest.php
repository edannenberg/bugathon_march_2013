<?php

/**
 * @group module:Mage_Catalog
 * @magentoDataFixture Mage/Catalog/_files/product_large_configurables.php
 */
class Mage_Catalog_Model_Resource_Product_Configurable_CollectionTest
    extends PHPUnit_Framework_TestCase
{
    /**
     * These values should match the settings in the fixture
     * Mage/Catalog/_files/product_large_configurables.php
     *
     * @return array
     */
    public function productCollectionDataProvider()
    {
        $numConfigurables = 2;
        $numAssociatedSimples = 20;
        return array(
            array(
                $numConfigurables,
                $numAssociatedSimples
            )
        );
    }

    /**
     * @test
     * @dataProvider productCollectionDataProvider
     */
    public function loadProductCollection($numConfigurables, $numAssociatedSimples)
    {
        /** @var $collection Mage_Catalog_Model_Resource_Product_Collection */
        $collection = new Mage_Catalog_Model_Resource_Product_Collection();
        $collection->addFieldToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
                   ->addStoreFilter(Mage::app()->getDefaultStoreView()->getId());

        $this->assertEquals(
            $numConfigurables,
            $collection->count(),
            'Expected number of configurable products in the collection does not match expected one.'
        );

        foreach ($collection as $product) {
            $this->assertConfigurableIntegrity(
                $product,
                $numAssociatedSimples
            );
        }
    }

    /**
     * @test
     * @dataProvider productCollectionDataProvider
     */
    public function loadTimeIsFasterForProductCollectionWithFlagThenWithoutFlag(
        $numConfigurables,
        $numAssociatedSimples
    )
    {
        /** @var $collectionWithoutFlag Mage_Catalog_Model_Resource_Product_Collection */
        $collectionWithoutFlag = new Mage_Catalog_Model_Resource_Product_Collection();
        $collectionWithoutFlag->addFieldToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
        $collectionWithoutFlag->addStoreFilter(Mage::app()->getDefaultStoreView()->getId());

        $this->assertEquals(
            $numConfigurables,
            $collectionWithoutFlag->count(),
            'Product count in collection without flag is wrong.'
        );

        /** @var $collectionWithFlag Mage_Catalog_Model_Product[]|Mage_Catalog_Model_Resource_Product_Collection */
        $collectionWithFlag = clone $collectionWithoutFlag;
        $collectionWithFlag->setFlag(
            Mage_Catalog_Model_Resource_Product_Collection::FLAG_LOAD_ASSOCIATED_DATA,
            true
        );

        $this->assertEquals(
            $numConfigurables,
            $collectionWithFlag->count(),
            'Product count in collection with flag is wrong.'
        );

        /* @var $typeInstance Mage_Catalog_Model_Product_Type_Configurable */
        $withFlagLoadStart = microtime(true);
        foreach ($collectionWithFlag as $product) {
            // Test integrity of the configurable products with collection flag
            $this->assertConfigurableIntegrity(
                $product,
                $numAssociatedSimples
            );
        }
        $withFlagLoadTime = microtime(true) - $withFlagLoadStart;

        $withoutFlagLoadStart = microtime(true);
        foreach ($collectionWithoutFlag as $product) {
            // Test integrity of the configurable products without collection flag
            $this->assertConfigurableIntegrity(
                $product,
                $numAssociatedSimples
            );
        }
        $withoutLoadTime= microtime(true) - $withoutFlagLoadStart;

        $this->assertLessThan(
            $withoutLoadTime / 2, # Should be at least 2 times faster
            $withFlagLoadTime,
            "Collection load time with set flag is slower then load time without the flag"
        );
    }

    /**
     * Asserts integrity of configurable products
     *
     * @param Mage_Catalog_Model_Product $product
     * @param int $numActiveAssociatedSimples
     */
    public function assertConfigurableIntegrity($product, $numAssociatedSimples)
    {
        $configurableIndex = substr($product->getSku(), 12);
        $expectedAssiatedSimplePrefix = 'simple_' . $configurableIndex . '.';
        $expectedAttributeConfigPrefix = 'test_large_configurable' . $configurableIndex;

        /* @var $typeInstance Mage_Catalog_Model_Product_Type_Configurable */
        /* @var $usedProducts Mage_Catalog_Model_Product[] */
        /* @var $usedAttributes Mage_Catalog_Model_Product_Type_Configurable_Attribute[] */
        $typeInstance = $product->getTypeInstance(true);

        $usedAttributes = $typeInstance->getConfigurableAttributes($product);

        $this->assertCount(
            1,
            $usedAttributes,
            'Expected configurable attributes count does not match actual'
        );

        foreach ($usedAttributes as $attribute) {
            $this->assertStringStartsWith(
                $expectedAttributeConfigPrefix,
                $attribute->getProductAttribute()->getAttributeCode(),
                'Configurable attribute is not valid'
            );
        }

        $usedProducts = (array) $typeInstance->getUsedProducts(null, $product);
        $this->assertCount(
            $numAssociatedSimples,
            $usedProducts,
            'Expected associated simple products count does not match actual'
        );

        foreach ($usedProducts as $simpleProduct) {
            $this->assertStringStartsWith(
                $expectedAssiatedSimplePrefix,
                $simpleProduct->getSku(),
                'Simple product does not match configurable index'
            );

            $this->assertEquals(
                10,
                $simpleProduct->getFinalPrice(),
                'Price is not loaded for simple product' . $simpleProduct->getSku()
            );

            $simpleIndex = substr(
                $simpleProduct->getSku(),
                strlen($expectedAssiatedSimplePrefix)
            );

            foreach ($usedAttributes as $attribute) {
                $this->assertEquals(
                    'Option ' . $configurableIndex . '.' . ($simpleIndex+1),
                    $simpleProduct->getAttributeText(
                        $attribute->getProductAttribute()->getAttributeCode()
                    )
                );
            }
        }




    }
}
