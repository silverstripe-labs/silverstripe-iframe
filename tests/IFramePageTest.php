<?php

namespace SilverStripe\IFrame\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Director;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\IFrame\IFramePage;
use SilverStripe\IFrame\IFramePageController;

class IFramePageTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testGetClass()
    {
        $iframe = new IFramePage();
        $iframe->AutoHeight = 1;
        $iframe->getClass();

        $this->assertContains('iframepage-height-auto', $iframe->getClass());

        $iframe->AutoHeight = 0;
        $iframe->getClass();

        $this->assertNotContains('iframepage-height-auto', $iframe->getClass());
    }

    public function testGetStyle()
    {
        $iframe = new IFramePage();

        $iframe->FixedHeight = 0;
        $iframe->getStyle();
        $this->assertContains('height: 800px', $iframe->getStyle(), 'Height defaults to 800 if not set.');

        $iframe->FixedHeight = 100;
        $iframe->getStyle();
        $this->assertContains('height: 100px', $iframe->getStyle(), 'Fixed height is settable');

        $iframe->AutoWidth = 1;
        $iframe->FixedWidth = '200';
        $this->assertContains('width: 100%', $iframe->getStyle(), 'Auto width overrides fixed width');

        $iframe->AutoWidth = 0;
        $iframe->FixedWidth = '200';
        $this->assertContains('width: 200px', $iframe->getStyle(), 'Fixed width is settable');
    }

    public function testAllowedUrls()
    {
        $iframe = new IFramePage();

        $tests = array(
            'allowed' => array(
                'http://anything',
                'https://anything',
                'page',
                'sub-page/link',
                'page/link',
                'page.html',
                'page.htm',
                'page.phpissoawesomewhywouldiuseanythingelse',
                '//url.com/page',
                '/root/page/link',
                'http://intranet:8888',
                'http://javascript:8080',
                'http://username:password@hostname/path?arg=value#anchor'
            ),
            'banned' => array(
                'javascript:alert',
                'tel:0210001234',
                'ftp://url',
                'ssh://1.2.3.4',
                'ssh://url.com/page'
            )
        );

        foreach ($tests['allowed'] as $url) {
            $iframe->IFrameURL = $url;
            $iframe->write();
            $this->assertContains($iframe->IFrameURL, $url);
        }

        foreach ($tests['banned'] as $url) {
            $iframe->IFrameURL = $url;
            $this->setExpectedException(ValidationException::class);
            $iframe->write();
        }
    }

    public function testForceProtocol()
    {
        $origServer = $_SERVER;

        $page = new IFramePage();
        $page->URLSegment = 'iframe';
        $page->IFrameURL = 'http://target.com';

        Config::modify()->set(Director::class, 'alternate_protocol', 'http');
        Config::modify()->set(Director::class, 'alternate_base_url', 'http://host.com');
        $page->ForceProtocol = '';
        $controller = new IFramePageController($page);
        $controller->doInit();
        $response = $controller->getResponse();
        $this->assertNull($response->getHeader('Location'));

        Config::modify()->set(Director::class, 'alternate_protocol', 'https');
        Config::modify()->set(Director::class, 'alternate_base_url', 'https://host.com');
        $page->ForceProtocol = '';
        $controller = new IFramePageController($page);
        $controller->doInit();
        $response = $controller->getResponse();
        $this->assertNull($response->getHeader('Location'));

        Config::modify()->set(Director::class, 'alternate_protocol', 'http');
        Config::modify()->set(Director::class, 'alternate_base_url', 'http://host.com');
        $page->ForceProtocol = 'http://';
        $controller = new IFramePageController($page);
        $controller->doInit();
        $response = $controller->getResponse();
        $this->assertNull($response->getHeader('Location'));

        Config::modify()->set(Director::class, 'alternate_protocol', 'http');
        Config::modify()->set(Director::class, 'alternate_base_url', 'http://host.com');
        $page->ForceProtocol = 'https://';
        $controller = new IFramePageController($page);
        $controller->doInit();
        $response = $controller->getResponse();
        $this->assertEquals($response->getHeader('Location'), 'https://host.com/iframe/');

        Config::modify()->set(Director::class, 'alternate_protocol', 'https');
        Config::modify()->set(Director::class, 'alternate_base_url', 'https://host.com');
        $page->ForceProtocol = 'http://';
        $controller = new IFramePageController($page);
        $controller->doInit();
        $response = $controller->getResponse();
        $this->assertEquals($response->getHeader('Location'), 'http://host.com/iframe/');

        $_SERVER = $origServer;
    }
}
