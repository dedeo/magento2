<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace Magento\Store\Model;

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Filesystem\DirectoryList;

class StoreTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var array
     */
    protected $modelParams;

    /**
     * @var \Magento\Store\Model\Store|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $model;

    /**
     * @var array
     */
    protected $existingCookies;

    protected function setUp()
    {
        $this->model = $this->_getStoreModel();
        $this->existingCookies = $_COOKIE;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|\Magento\Store\Model\Store
     */
    protected function _getStoreModel()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->modelParams = [
            'context' => $objectManager->get('Magento\Framework\Model\Context'),
            'registry' => $objectManager->get('Magento\Framework\Registry'),
            'resource' => $objectManager->get('Magento\Store\Model\Resource\Store'),
            'coreFileStorageDatabase' => $objectManager->get('Magento\Core\Helper\File\Storage\Database'),
            'configCacheType' => $objectManager->get('Magento\Framework\App\Cache\Type\Config'),
            'url' => $objectManager->get('Magento\Framework\Url'),
            'request' => $objectManager->get('Magento\Framework\App\RequestInterface'),
            'configDataResource' => $objectManager->get('Magento\Core\Model\Resource\Config\Data'),
            'filesystem' => $objectManager->get('Magento\Framework\Filesystem'),
            'config' => $objectManager->get('Magento\Framework\App\Config\ReinitableConfigInterface'),
            'storeManager' => $objectManager->get('Magento\Store\Model\StoreManager'),
            'sidResolver' => $objectManager->get('Magento\Framework\Session\SidResolverInterface'),
            'cookieMetadataFactory' => $objectManager->get('Magento\Framework\Stdlib\Cookie\CookieMetadataFactory'),
            'cookieManager' => $objectManager->get('Magento\Framework\Stdlib\CookieManagerInterface'),
            'httpContext' => $objectManager->get('Magento\Framework\App\Http\Context'),
            'session' => $objectManager->get('Magento\Framework\Session\SessionManagerInterface'),
            'currencyFactory' => $objectManager->get('Magento\Directory\Model\CurrencyFactory'),
            'currencyInstalled' => 'system/currency/installed',
        ];

        return $this->getMock('Magento\Store\Model\Store', ['getUrl'], $this->modelParams);
    }

    protected function tearDown()
    {
        $this->model = null;
        $_COOKIE = $this->existingCookies;
    }

    public function testSetCookie()
    {
        $model = $this->getMock('Magento\Store\Model\Store', ['getStorePath'], $this->modelParams);
        $model->expects($this->once())
            ->method('getStorePath')
            ->will($this->returnValue('/'));
        $storeCode = 'store code';
        $this->assertArrayNotHasKey(Store::COOKIE_NAME, $_COOKIE);
        $model->setCode($storeCode);
        $model->setCookie();
        $this->assertEquals($storeCode, $_COOKIE[Store::COOKIE_NAME]);
    }

    public function testGetStoreCodeFromCookie()
    {
        $storeCode = 'store code';
        $_COOKIE[Store::COOKIE_NAME] = $storeCode;
        $this->assertEquals($storeCode, $this->model->getStoreCodeFromCookie());
    }

    public function testDeleteCookie()
    {
        $storeCode = 'store code';
        $_COOKIE[Store::COOKIE_NAME] = $storeCode;
        $this->assertArrayHasKey(Store::COOKIE_NAME, $_COOKIE);
        $this->model->deleteCookie();
        $this->assertArrayNotHasKey(Store::COOKIE_NAME, $_COOKIE);
    }

    /**
     * @dataProvider loadDataProvider
     */
    public function testLoad($loadId, $expectedId)
    {
        $this->model->load($loadId);
        $this->assertEquals($expectedId, $this->model->getId());
    }

    /**
     * @return array
     */
    public function loadDataProvider()
    {
        return [[1, 1], ['default', 1], ['nostore', null]];
    }

    public function testSetGetWebsite()
    {
        $this->assertFalse($this->model->getWebsite());
        $website = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(
            'Magento\Store\Model\StoreManagerInterface'
        )->getWebsite();
        $this->model->setWebsite($website);
        $actualResult = $this->model->getWebsite();
        $this->assertSame($website, $actualResult);
    }

    public function testSetGetGroup()
    {
        $this->assertFalse($this->model->getGroup());
        $storeGroup = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(
            'Magento\Store\Model\StoreManager'
        )->getGroup();
        $this->model->setGroup($storeGroup);
        $actualResult = $this->model->getGroup();
        $this->assertSame($storeGroup, $actualResult);
    }

    /**
     * Isolation is enabled, as we pollute config with rewrite values
     *
     * @param string $type
     * @param bool $useRewrites
     * @param bool $useStoreCode
     * @param string $expected
     * @dataProvider getBaseUrlDataProvider
     * @magentoAppIsolation enabled
     */
    public function testGetBaseUrl($type, $useRewrites, $useStoreCode, $expected)
    {
        /* config operations require store to be loaded */
        $this->model->load('default');
        \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(
            'Magento\Framework\App\Config\MutableScopeConfigInterface'
        )->setValue(
            \Magento\Store\Model\Store::XML_PATH_USE_REWRITES,
            $useRewrites,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(
            'Magento\Framework\App\Config\MutableScopeConfigInterface'
        )->setValue(
            \Magento\Store\Model\Store::XML_PATH_STORE_IN_URL,
            $useStoreCode,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $actual = $this->model->getBaseUrl($type);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return array
     */
    public function getBaseUrlDataProvider()
    {
        return [
            [\Magento\Framework\UrlInterface::URL_TYPE_WEB, false, false, 'http://localhost/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_WEB, false, true, 'http://localhost/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_WEB, true, false, 'http://localhost/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_WEB, true, true, 'http://localhost/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_LINK, false, false, 'http://localhost/index.php/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_LINK, false, true, 'http://localhost/index.php/default/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_LINK, true, false, 'http://localhost/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_LINK, true, true, 'http://localhost/default/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_DIRECT_LINK, false, false, 'http://localhost/index.php/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_DIRECT_LINK, false, true, 'http://localhost/index.php/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_DIRECT_LINK, true, false, 'http://localhost/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_DIRECT_LINK, true, true, 'http://localhost/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_STATIC, false, false, 'http://localhost/pub/static/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_STATIC, false, true, 'http://localhost/pub/static/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_STATIC, true, false, 'http://localhost/pub/static/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_STATIC, true, true, 'http://localhost/pub/static/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_MEDIA, false, false, 'http://localhost/pub/media/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_MEDIA, false, true, 'http://localhost/pub/media/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_MEDIA, true, false, 'http://localhost/pub/media/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_MEDIA, true, true, 'http://localhost/pub/media/']
        ];
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetBaseUrlInPub()
    {
        \Magento\TestFramework\Helper\Bootstrap::getInstance()->reinitialize(
            [
                Bootstrap::INIT_PARAM_FILESYSTEM_DIR_PATHS => [
                    DirectoryList::PUB => [DirectoryList::URL_PATH => ''],
                ],
            ]
        );
        $this->model = $this->_getStoreModel();
        $this->model->load('default');

        $this->assertEquals(
            'http://localhost/pub/static/',
            $this->model->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_STATIC)
        );
        $this->assertEquals(
            'http://localhost/pub/media/',
            $this->model->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA)
        );
    }

    /**
     * Isolation is enabled, as we pollute config with rewrite values
     *
     * @param string $type
     * @param bool $useCustomEntryPoint
     * @param bool $useStoreCode
     * @param string $expected
     * @dataProvider getBaseUrlForCustomEntryPointDataProvider
     * @magentoAppIsolation enabled
     */
    public function testGetBaseUrlForCustomEntryPoint($type, $useCustomEntryPoint, $useStoreCode, $expected)
    {
        /* config operations require store to be loaded */
        $this->model->load('default');
        \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(
            'Magento\Framework\App\Config\MutableScopeConfigInterface'
        )->setValue(
            \Magento\Store\Model\Store::XML_PATH_USE_REWRITES,
            false,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(
            'Magento\Framework\App\Config\MutableScopeConfigInterface'
        )->setValue(
            \Magento\Store\Model\Store::XML_PATH_STORE_IN_URL,
            $useStoreCode,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        // emulate custom entry point
        $_SERVER['SCRIPT_FILENAME'] = 'custom_entry.php';
        if ($useCustomEntryPoint) {
            $property = new \ReflectionProperty($this->model, '_isCustomEntryPoint');
            $property->setAccessible(true);
            $property->setValue($this->model, $useCustomEntryPoint);
        }
        $actual = $this->model->getBaseUrl($type);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return array
     */
    public function getBaseUrlForCustomEntryPointDataProvider()
    {
        return [
            [\Magento\Framework\UrlInterface::URL_TYPE_LINK, false, false, 'http://localhost/custom_entry.php/'],
            [
                \Magento\Framework\UrlInterface::URL_TYPE_LINK,
                false,
                true,
                'http://localhost/custom_entry.php/default/'
            ],
            [\Magento\Framework\UrlInterface::URL_TYPE_LINK, true, false, 'http://localhost/index.php/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_LINK, true, true, 'http://localhost/index.php/default/'],
            [
                \Magento\Framework\UrlInterface::URL_TYPE_DIRECT_LINK,
                false,
                false,
                'http://localhost/custom_entry.php/'
            ],
            [
                \Magento\Framework\UrlInterface::URL_TYPE_DIRECT_LINK,
                false,
                true,
                'http://localhost/custom_entry.php/'
            ],
            [\Magento\Framework\UrlInterface::URL_TYPE_DIRECT_LINK, true, false, 'http://localhost/index.php/'],
            [\Magento\Framework\UrlInterface::URL_TYPE_DIRECT_LINK, true, true, 'http://localhost/index.php/']
        ];
    }

    public function testGetDefaultCurrency()
    {
        /* currency operations require store to be loaded */
        $this->model->load('default');
        $this->assertEquals($this->model->getDefaultCurrencyCode(), $this->model->getDefaultCurrency()->getCode());
    }

    public function testIsCanDelete()
    {
        $this->assertFalse($this->model->isCanDelete());
        $this->model->load(1);
        $this->assertFalse($this->model->isCanDelete());
        $this->model->setId(100);
        $this->assertTrue($this->model->isCanDelete());
    }

    public function testGetCurrentUrl()
    {
        $this->model->load('admin');
        $this->model->expects($this->any())->method('getUrl')->will($this->returnValue('http://localhost/index.php'));
        $this->assertStringEndsWith('default', $this->model->getCurrentUrl());
        $this->assertStringEndsNotWith('default', $this->model->getCurrentUrl(false));
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoAppArea adminhtml
     * @magentoDbIsolation enabled
     */
    public function testCRUD()
    {
        $this->model->setData(
            [
                'code' => 'test',
                'website_id' => 1,
                'group_id' => 1,
                'name' => 'test name',
                'sort_order' => 0,
                'is_active' => 1,
            ]
        );
        $crud = new \Magento\TestFramework\Entity(
            $this->model, ['name' => 'new name'], 'Magento\Store\Model\Store'
        );
        $crud->testCrud();
    }

    /**
     * @param array $badStoreData
     *
     * @dataProvider saveValidationDataProvider
     * @magentoAppIsolation enabled
     * @magentoAppArea adminhtml
     * @magentoDbIsolation enabled
     * @expectedException \Magento\Framework\Model\Exception
     */
    public function testSaveValidation($badStoreData)
    {
        $normalStoreData = [
            'code' => 'test',
            'website_id' => 1,
            'group_id' => 1,
            'name' => 'test name',
            'sort_order' => 0,
            'is_active' => 1,
        ];
        $data = array_merge($normalStoreData, $badStoreData);
        $this->model->setData($data);
        $this->model->save();
    }

    /**
     * @return array
     */
    public static function saveValidationDataProvider()
    {
        return [
            'empty store name' => [['name' => '']],
            'empty store code' => [['code' => '']],
            'invalid store code' => [['code' => '^_^']]
        ];
    }

    /**
     * @dataProvider isUseStoreInUrlDataProvider
     */
    public function testIsUseStoreInUrl($storeInUrl, $disableStoreInUrl, $expectedResult)
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $configMock = $this->getMock('Magento\Framework\App\Config\ReinitableConfigInterface');
        $appStateMock = $this->getMock('Magento\Framework\App\State', [], [], '', false, false);

        $params = $this->modelParams;
        $params['context'] = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            'Magento\Framework\Model\Context',
            ['appState' => $appStateMock]
        );

        $configMock->expects(
            $this->any()
        )->method(
            'getValue'
        )->with(
            $this->stringContains(\Magento\Store\Model\Store::XML_PATH_STORE_IN_URL)
        )->will(
            $this->returnValue($storeInUrl)
        );
        $params['config'] = $configMock;
        $model = $objectManager->create('Magento\Store\Model\Store', $params);
        $model->setDisableStoreInUrl($disableStoreInUrl);
        $this->assertEquals($expectedResult, $model->isUseStoreInUrl());
    }

    /**
     * @see self::testIsUseStoreInUrl;
     * @return array
     */
    public function isUseStoreInUrlDataProvider()
    {
        return [
            [true, null, true],
            [false, null, false],
            [true, true, false],
            [true, false, true]
        ];
    }

    /**
     * @dataProvider isCurrentlySecureDataProvider
     *
     * @param bool $expected
     * @param array $serverValues
     * @magentoConfigFixture current_store web/secure/offloader_header SSL_OFFLOADED
     * @magentoConfigFixture current_store web/secure/base_url https://example.com:80
     */
    public function testIsCurrentlySecure($expected, $serverValues)
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        /** @var \Magento\Store\Model\Store $model */
        $model = $objectManager->create('Magento\Store\Model\Store');

        $server = $_SERVER;
        foreach ($serverValues as $key => $value) {
            $_SERVER[$key] = $value;
        }

        $this->assertEquals($expected, $model->isCurrentlySecure());
        $_SERVER = $server;
    }

    public function isCurrentlySecureDataProvider()
    {
        return [
            [true, ['HTTPS' => 'on']],
            [true, ['SSL_OFFLOADED' => 'https']],
            [true, ['HTTP_SSL_OFFLOADED' => 'https']],
            [true, ['SERVER_PORT' => 80]],
            [false, []],
        ];
    }

    /**
     * @magentoConfigFixture current_store web/secure/offloader_header SSL_OFFLOADED
     * @magentoConfigFixture current_store web/secure/base_url 
     */
    public function testIsCurrentlySecureNoSecureBaseUrl()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        /** @var \Magento\Store\Model\Store $model */
        $model = $objectManager->create('Magento\Store\Model\Store');

        $server = $_SERVER;
        $_SERVER['SERVER_PORT'] = 80;

        $this->assertFalse($model->isCurrentlySecure());
        $_SERVER = $server;
    }
}
