<?php

declare(strict_types=1);

namespace Sov\Sovendus\Components;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RequestStack;
use Sov\Sovendus\Service\ConfigService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

class SovendusData extends Struct
{
    protected ConfigService $configService;
    protected ?OrderEntity $order;
    protected ?CustomerEntity $customer;
    protected ?CurrencyEntity $currency;

    public bool $enabled;
    public int $trafficSourceNumber;
    public int $trafficMediumNumber;
    public string $bannerLocation;
    public string $consumerCity;
    public string $consumerCountry;
    public string $consumerEmail;
    public string $consumerFirstName;
    public string $consumerLastName;
    public string $consumerPhone;
    public string $consumerSalutation;
    public string $consumerStreet;
    public string $consumerStreetNumber;
    public string $consumerZipcode;
    public string $orderCurrency;
    public string $orderId;
    public string $sessionId;
    public string $usedCouponCode;
    public float $orderValue;
    public int $timestamp;
    public array $bannerLocationConstants;

    public function __construct()
    {
        $this->enabled = false;
        $this->trafficSourceNumber = 0;
        $this->trafficMediumNumber = 0;
        $this->bannerLocation = Config::BANNER_POSITION_BELOW_FINISH_TEASER;
        $this->bannerLocationConstants = array(
            'above' => Config::BANNER_POSITION_ABOVE_FINISH_TEASER,
            'below' => Config::BANNER_POSITION_BELOW_FINISH_TEASER
        );
        $this->consumerCity = '';
        $this->consumerCountry = '';
        $this->consumerEmail = '';
        $this->consumerFirstName = '';
        $this->consumerLastName = '';
        $this->consumerPhone = '';
        $this->consumerSalutation = "";
        $this->consumerStreet = '';
        $this->consumerStreetNumber = '';
        $this->consumerZipcode = '';
        $this->orderCurrency = '';
        $this->orderId = '';
        $this->sessionId = '';
        $this->usedCouponCode = '';
        $this->orderValue = 0;
        $this->timestamp = time();
    }

    public function initializeSovendusData(RequestStack $requestStack, ConfigService $configService, ?OrderEntity $order, ?CustomerEntity $customer, ?CurrencyEntity $currency)
    {
        $this->configService = $configService;
        $this->order = $order;
        $this->customer = $customer;
        $this->currency = $currency;
        $this->timestamp = time();

        $this->initializeCurrencyData();
        $this->initializeCustomerData();
        $this->initializeOrderData();
        $this->initializeSessionId($requestStack);
    }
    protected function initializeCurrencyData(): void
    {
        if (!is_null($this->currency)) {
            $this->orderCurrency = $this->currency->getIsoCode();
        }
    }

    protected function initializeCustomerData(): void
    {
        if (!is_null($this->customer)) {
            $this->consumerEmail = $this->customer->getEmail();
            $this->consumerFirstName = $this->customer->getFirstName();
            $this->consumerLastName = $this->customer->getLastName();
            if (!is_null($this->customer->getSalutation())) {
                $this->consumerSalutation = $this->getSalutationByKey($this->customer->getSalutation()->getSalutationKey());
            }
            if (!is_null($this->customer->getDefaultBillingAddress())) {
                $this->initializeAddressData($this->customer->getDefaultBillingAddress());
            }
        }
    }

    protected function initializeOrderData(): void
    {
        if (!is_null($this->order)) {
            $this->orderValue = $this->calculateOrderValue($this->order);
            if (!is_null($this->order->getOrderNumber())) {
                $this->orderId = $this->order->getOrderNumber();
            }
            if (!is_null($this->order->getLineItems())) {
                $promotions = $this->order->getLineItems()->filterByType(\Shopware\Core\Checkout\Cart\LineItem\LineItem::PROMOTION_LINE_ITEM_TYPE)->getElements();
                foreach ($promotions as $promotion) {
                    if (!is_null($promotion->getPayload()) && isset($promotion->getPayload()['code']) && ($promotion->getPayload()['code'] != '')) {
                        $this->usedCouponCode = $promotion->getPayload()['code'];
                        break;
                    }
                }
            }
        }
    }

    protected function initializeAddressData(CustomerAddressEntity $address): void
    {
        $this->consumerZipcode = $address->getZipcode();
        $this->consumerCity = $address->getCity();
        $this->initializeStreetAndStreetNumber($address->getStreet());
        if (!is_null($address->getCountry())) {
            if (!is_null($address->getCountry()->getTranslated())) {
                if (isset($address->getCountry()->getTranslated()['name'])) {
                    $this->consumerCountry = $address->getCountry()->getTranslated()['name'];
                }
            }
        }
        if (!is_null($address->getPhoneNumber())) {
            $this->consumerPhone = $address->getPhoneNumber();
        }
    }

    /**
     * split up housenumber and street
     */
    protected function initializeStreetAndStreetNumber(string $street)
    {
        if ((strlen($street) > 0) && preg_match_all('#([0-9/ -]+ ?[a-zA-Z]?(\s|$))#', trim($street), $match)) {
            $housenr = end($match[0]);
            $this->consumerStreet = trim(str_replace(array($housenr, '/'), '', $street));
            $this->consumerStreetNumber = trim($housenr);
        } else {
            $this->consumerStreet = $street;
        }
    }

    protected function initializeSessionId(RequestStack $requestStack)
    {
        $session = $requestStack->getSession();
        $this->sessionId = $session->getId();
    }

    /**
     * net orderValue without shipping costs
     * @param OrderEntity $order
     * @return float
     */
    protected function calculateOrderValue(OrderEntity $order): float
    {
        $shippingCostsNet = $order->getShippingCosts()->getTotalPrice() - $order->getShippingCosts()->getCalculatedTaxes()->getAmount();
        return $order->getAmountNet() - $shippingCostsNet;
    }

    /**
     * 
     * @param string $salutationKey
     * @return string
     */
    public function getSalutationByKey(string $salutationKey): string
    {
        return ($salutationKey == 'mr') ? 'Mr.' : (($salutationKey == 'mrs') ? "Mrs." : "");
    }

    /**
     * 
     * @return bool
     */
    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * 
     * @return int
     */
    public function getTrafficSourceNumber(): int
    {
        return $this->trafficSourceNumber;
    }

    /**
     * 
     * @return int
     */
    public function getTrafficMediumNumber(): int
    {
        return $this->trafficMediumNumber;
    }

    /**
     * 
     * @return string
     */
    public function getbannerLocation(): string
    {
        return $this->bannerLocation;
    }

    /**
     * 
     * @return array
     */
    public function getbannerLocationConstants(): array
    {
        return $this->bannerLocationConstants;
    }

    /**
     * 
     * @return string
     */
    public function getConsumerCity(): string
    {
        return $this->consumerCity;
    }

    /**
     * 
     * @return string
     */
    public function getConsumerCountry(): string
    {
        return $this->consumerCountry;
    }

    /**
     * 
     * @return string
     */
    public function getConsumerEmail(): string
    {
        return $this->consumerEmail;
    }

    /**
     * 
     * @return string
     */
    public function getConsumerFirstName(): string
    {
        return $this->consumerFirstName;
    }

    /**
     * 
     * @return string
     */
    public function getConsumerLastName(): string
    {
        return $this->consumerLastName;
    }

    /**
     * 
     * @return string
     */
    public function getConsumerPhone(): string
    {
        return $this->consumerPhone;
    }

    /**
     * 
     * @return string
     */
    public function getConsumerSalutation(): string
    {
        return $this->consumerSalutation;
    }

    /**
     * 
     * @return string
     */
    public function getConsumerStreet(): string
    {
        return $this->consumerStreet;
    }

    /**
     * 
     * @return string
     */
    public function getConsumerStreetNumber(): string
    {
        return $this->consumerStreetNumber;
    }

    /**
     * 
     * @return string
     */
    public function getConsumerZipcode(): string
    {
        return $this->consumerZipcode;
    }

    /**
     * 
     * @return string
     */
    public function getOrderCurrency(): string
    {
        return $this->orderCurrency;
    }

    /**
     * 
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * 
     * @return string
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * 
     * @return string
     */
    public function getUsedCouponCode(): string
    {
        return $this->usedCouponCode;
    }

    /**
     * 
     * @return float
     */
    public function getOrderValue(): float
    {
        return $this->orderValue;
    }

    /**
     * 
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

}