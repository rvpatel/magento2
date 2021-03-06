<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace Magento\Tax\Model\Sales\Total\Quote;

/**
 * Test class for \Magento\Tax\Model\Sales\Total\Quote\Tax
 */
use Magento\TestFramework\Helper\ObjectManager;

class CommonTaxCollectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector
     */
    private $commonTaxCollector;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Magento\Tax\Model\Config
     */
    private $taxConfig;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Magento\Quote\Model\Quote\Address
     */
    private $address;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Magento\Quote\Model\Quote
     */
    private $quote;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Magento\Store\Model\Store
     */
    private $store;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|
     */
    protected $taxClassKeyDataObjectFactoryMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|
     */
    protected $quoteDetailsItemDataObjectFactoryMock;

    /**
     * @var \Magento\Tax\Api\Data\QuoteDetailsItemInterface
     */
    protected $quoteDetailsItemDataObject;

    /**
     * @var \Magento\Tax\Api\Data\TaxClassKeyInterface
     */
    protected $taxClassKeyDataObject;

    public function setUp()
    {
        $objectManager = new ObjectManager($this);

        $this->taxConfig = $this->getMockBuilder('\Magento\Tax\Model\Config')
            ->disableOriginalConstructor()
            ->setMethods(['getShippingTaxClass', 'shippingPriceIncludesTax'])
            ->getMock();

        $this->store = $this->getMockBuilder('\Magento\Store\Model\Store')
            ->disableOriginalConstructor()
            ->setMethods(['__wakeup'])
            ->getMock();

        $this->quote = $this->getMockBuilder('\Magento\Quote\Model\Quote')
            ->disableOriginalConstructor()
            ->setMethods(['__wakeup', 'getStore'])
            ->getMock();

        $this->quote->expects($this->any())
            ->method('getStore')
            ->will($this->returnValue($this->store));

        $this->address = $this->getMockBuilder('\Magento\Quote\Model\Quote\Address')
            ->disableOriginalConstructor()
            ->setMethods(['__wakeup', 'getQuote', 'getShippingDiscountAmount', 'getBaseShippingDiscountAmount'])
            ->getMock();

        $this->address->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($this->quote));
        $methods = ['create'];
        $this->quoteDetailsItemDataObject = $objectManager->getObject('Magento\Tax\Model\Sales\Quote\ItemDetails');
        $this->taxClassKeyDataObject = $objectManager->getObject('Magento\Tax\Model\TaxClass\Key');
        $this->quoteDetailsItemDataObjectFactoryMock
            = $this->getMock('Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory', $methods, [], '', false);
        $this->quoteDetailsItemDataObjectFactoryMock->expects($this->any())
            ->method('create')
            ->willReturn($this->quoteDetailsItemDataObject);
        $this->taxClassKeyDataObjectFactoryMock =
            $this->getMock('Magento\Tax\Api\Data\TaxClassKeyInterfaceFactory', $methods, [], '', false);
        $this->taxClassKeyDataObjectFactoryMock->expects($this->any())
            ->method('create')
            ->willReturn($this->taxClassKeyDataObject);
        $this->commonTaxCollector = $objectManager->getObject(
            'Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector',
            [
                'taxConfig' => $this->taxConfig,
                'quoteDetailsItemDataObjectFactory' => $this->quoteDetailsItemDataObjectFactoryMock,
                'taxClassKeyDataObjectFactory' => $this->taxClassKeyDataObjectFactoryMock
            ]
        );
    }

    /**
     * @param array $addressData
     * @param bool $useBaseCurrency
     * @param string $shippingTaxClass
     * @param bool $shippingPriceInclTax
     * @dataProvider getShippingDataObjectDataProvider
     */
    public function testGetShippingDataObject(
        array $addressData,
        $useBaseCurrency,
        $shippingTaxClass,
        $shippingPriceInclTax
    ) {
        $baseShippingAmount = $addressData['base_shipping_amount'];
        $shippingAmount = $addressData['shipping_amount'];
        $this->taxConfig->expects($this->any())
            ->method('getShippingTaxClass')
            ->with($this->store)
            ->will($this->returnValue($shippingTaxClass));
        $this->taxConfig->expects($this->any())
            ->method('shippingPriceIncludesTax')
            ->with($this->store)
            ->will($this->returnValue($shippingPriceInclTax));
        $this->address
             ->expects($this->atLeastOnce())
             ->method('getShippingDiscountAmount')
             ->willReturn($shippingAmount);
        if ($shippingAmount) {
            if ($useBaseCurrency && $shippingAmount != 0) {
                $this->address
                    ->expects($this->once())
                    ->method('getBaseShippingDiscountAmount')
                    ->willReturn($baseShippingAmount);
                $expectedDiscountAmount = $baseShippingAmount;
            } else {
                $this->address->expects($this->never())->method('getBaseShippingDiscountAmount');
                $expectedDiscountAmount = $shippingAmount;
            }
        }
        foreach ($addressData as $key => $value) {
            $this->address->setData($key, $value);
        }
        $this->assertEquals($this->quoteDetailsItemDataObject,
            $this->commonTaxCollector->getShippingDataObject($this->address, $useBaseCurrency));
        if ($shippingAmount) {
            $this->assertEquals($expectedDiscountAmount, $this->quoteDetailsItemDataObject->getDiscountAmount());
        }
    }

    public function getShippingDataObjectDataProvider()
    {
        $data = [
            'free_shipping' => [
                'address' => [
                        'shipping_amount' => 0,
                        'base_shipping_amount' => 0,
                    ],
                'use_base_currency' => false,
                'shipping_tax_class' => 'shippingTaxClass',
                'shippingPriceInclTax' => true,
            ],
            'none_zero_none_base' => [
                'address' => [
                        'shipping_amount' => 10,
                        'base_shipping_amount' => 5,
                    ],
                'use_base_currency' => false,
                'shipping_tax_class' => 'shippingTaxClass',
                'shippingPriceInclTax' => true,
            ],
            'none_zero_base' => [
                'address' => [
                    'shipping_amount' => 10,
                    'base_shipping_amount' => 5,
                ],
                'use_base_currency' => true,
                'shipping_tax_class' => 'shippingTaxClass',
                'shippingPriceInclTax' => true,
            ],
        ];

        return $data;
    }
}
