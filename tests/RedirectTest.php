<?php
require_once dirname(__FILE__) . '/config.test.php';
require_once '../src/Ilib/Redirect.php';
require_once 'Ilib/ClassLoader.php';

class RedirectTest extends PHPUnit_Framework_TestCase
{
    private $table = 'redirect';
    private $server_vars = array();
    private $get_vars = array();
    private $session_id;
    private $db;
    protected $backupGlobals = false;

    function setUp()
    {
        $this->db = MDB2::singleton(TESTS_DB_DSN);
        $this->db->setOption('portability', MDB2_PORTABILITY_NONE);

        if (PEAR::isError($this->db)) {
            throw new Exception($this->db->getUserInfo());
        }
        $result = $this->db->exec('TRUNCATE redirect');
        $result = $this->db->exec('TRUNCATE redirect_parameter');
        $result = $this->db->exec('TRUNCATE redirect_parameter_value');

        $this->session_id = 'dfj3id3jdi3kdo3kdo3kdo3kdo3';

        $_SERVER['REQUEST_URI'] = 'http://example.php/from.php';
        $_SERVER['HTTP_HOST'] = 'http://localhost/';
    }

    function tearDown()
    {
        $result = $this->db->exec('TRUNCATE ' . $this->table);
    }

    function testConstruction()
    {
        $redirect = $this->createRedirect();
        $this->assertTrue(is_object($redirect));
    }

    function testGoRedirectAndsetDestination()
    {
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);
        $parameter_to_return_with = 'add_contact_id'; // activates the parameter sent back to the return page
        $this->assertEquals($destination_url . '?redirect_id=1', $url);
    }

    function testRecieveRedirectAndGetRedirect()
    {
        // go
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk/';
        $_SERVER['REQUEST_URI']  = 'state.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $standard_page_without_redirect = 'standard.php';
        $this->assertEquals($return_url . '&return_redirect_id=1', $redirect->getRedirect($standard_page_without_redirect));
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);
    }

    function testReturnsRedirect()
    {
        // go
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk/';
        $_SERVER['REQUEST_URI']  = 'state.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $standard_page_without_redirect = 'standard.php';
        $this->assertEquals($return_url . '&return_redirect_id=1', $redirect->getRedirect($standard_page_without_redirect));
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);

        // returning
        $_GET['return_redirect_id'] = 1;
        $redirect = Ilib_Redirect::returns($this->session_id, $this->db);
        $this->assertEquals(1, $redirect->getId());
    }

    function testLoadingARedirect()
    {
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);

        $redirect = new Ilib_Redirect($this->session_id, $this->db, 1);
        $this->assertEquals(1, $redirect->id);
    }

    /*
    The method is not public
    function testParseUrl() {
        $redirect = $this->createRedirect();
        $url = 'http://example.dk/index.php?id=2&uid=3';
        $this->assertEquals($url, $redirect->parseUrl($url));
    }
    */

    function testSetIdentifierBeforeSetDestination() {
        $redirect = $this->createRedirect();
        $this->assertTrue($redirect->setIdentifier('identifier1'));

    }

    function testSetIdentifierAfterSetDestination() {
        $redirect = $this->createRedirect();
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $redirect->setDestination($destination_url, $return_url);
        $this->assertTrue($redirect->setIdentifier('identifier1'));
    }

    function testThisUri()
    {
        $_SERVER['HTTPS']       = 'https://example.dk/index.php';
        $_SERVER['HTTP_HOST']   = 'example.dk';
        $_SERVER['REQUEST_URI'] = '/index.php';

        $redirect = $this->createRedirect();
        $this->assertEquals('https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], $redirect->thisUri());
        unset($_SERVER['HTTPS']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
    }

    /*
    method is not public
    function testAddQueryString() {
        $redirect = $this->createRedirect();
        // does not return anything at this point
        $redirect->addQueryString('another_id=3');
    }
    */

    /*
    method is not public
    function testMergeQueryString() {
        $redirect = $this->createRedirect();
        $this->assertEquals('index.php?id=1&another_id=2', $redirect->mergeQueryString('index.php?id=1', 'another_id=2'));
    }
    */

    function testDeleteWithNoIdReturnsTrue()
    {
        $redirect = $this->createRedirect();
        $this->assertTrue($redirect->delete());
    }

    function testDeleteExistingRedirectReturnsTrue()
    {
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);
        $this->assertTrue($redirect->delete());
    }

    function testAskParameter()
    {
        // go
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);
        $this->assertTrue($redirect->askParameter('param'));
    }

    function testSetParameterWithValidParameter()
    {
        // go
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);
        $redirect->askParameter('param');

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk/';
        $_SERVER['REQUEST_URI']  = 'state.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $this->assertTrue($redirect->setParameter('param', 120));
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);
    }

    function testSetParameterWithInvalidParameter()
    {
        // go
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);
        $redirect->askParameter('param');

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk/';
        $_SERVER['REQUEST_URI']  = 'state.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $this->assertFalse($redirect->setParameter('wrong_param', 120));
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);
    }

    function testIsMultipleParameter() {
        // go
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);
        $this->assertTrue($redirect->askParameter('param', 'multiple'));

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk/';
        $_SERVER['REQUEST_URI']  = 'state.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $this->assertTrue($redirect->isMultipleParameter('param') > 0);
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);
    }

    function testReturnFromRedirectWithSingleParameter()
    {
        // go
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);
        $redirect->askParameter('param');

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk/';
        $_SERVER['REQUEST_URI']  = 'state.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $redirect->setParameter('param', 120);
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);


        // returning
        $_GET['return_redirect_id']     = 1;
        $redirect = Ilib_Redirect::returns($this->session_id, $this->db);
        // notice that the returned format is string despite that the given is integer.
        $this->assertEquals('120', $redirect->getParameter('param'));
    }

    function testReturnFromRedirectWithMultiParameter()
    {
        // go
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);
        $redirect->askParameter('param', 'multiple');

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk/';
        $_SERVER['REQUEST_URI']  = 'state.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $redirect->setParameter('param', 120);
        $redirect->setParameter('param', 140);
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);

        // returning
        $_GET['return_redirect_id']     = 1;
        $redirect = Ilib_Redirect::returns($this->session_id, $this->db);
        // print_r($redirect->getParameter('param'));
        $this->assertEquals(array(120, 140), $redirect->getParameter('param'));
    }

    function testGetIdentifier() {
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $redirect->setIdentifier('identifier1');
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk/';
        $_SERVER['REQUEST_URI']  = 'state.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $this->assertEquals('identifier1', $redirect->getIdentifier('identifier1'));
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);
    }

    function testGetId() {
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk/';
        $_SERVER['REQUEST_URI']  = 'state.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $this->assertEquals(1, $redirect->getId());
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);
    }

    function testGetRedirectQueryString() {
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk/';
        $_SERVER['REQUEST_URI']  = 'state.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $this->assertEquals('redirect_id=1', $redirect->getRedirectQueryString());
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);
    }

    function testLoadRedirectAfterSubmit() {

        // go
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);

        // print_r($this->server_vars);
        // die;

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk';
        $_SERVER['REQUEST_URI']  = '/page.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);

        // recieve after submit to same page
        $_SERVER['HTTP_REFERER'] = $destination_url;
        $_SERVER['HTTP_HOST']    = 'example.dk';
        $_SERVER['REQUEST_URI']  = '/page.php';
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $this->assertEquals(1, $redirect->getId());

    }

    function testLoadRedirectAfterLoadFromAnotherPage() {

        // go
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk';
        $_SERVER['REQUEST_URI']  = '/page.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);

        // recieve after refer from another page
        $_SERVER['HTTP_REFERER'] = 'http://example.dk/another_page.php';
        $_SERVER['HTTP_HOST']    = 'example.dk';
        $_SERVER['REQUEST_URI']  = '/page.php';
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $this->assertEquals(0, $redirect->getId());

    }


    function testLoadRedirectAfterLoadFromAnotherPageAndThenFromTheSamePage() {

        // go
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $url = $redirect->setDestination($destination_url, $return_url);

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk';
        $_SERVER['REQUEST_URI']  = '/page.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);

        // recieve after refer from another page
        $_SERVER['HTTP_REFERER'] = 'http://example.dk/another_page.php';
        $_SERVER['HTTP_HOST']    = 'example.dk';
        $_SERVER['REQUEST_URI']  = '/page.php';
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);

        // and the recieve after the same page again
        // recieve after submit to same page
        $_SERVER['HTTP_REFERER'] = $destination_url;
        $_SERVER['HTTP_HOST']    = 'example.dk';
        $_SERVER['REQUEST_URI']  = '/page.php';
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $this->assertEquals(0, $redirect->getId());
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
    }

    function testLoadRedirectWithSecondRedirectInBetween()
    {
        // go
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url      = 'http://example.dk/state.php?id=1';
        $destination_url = 'http://example.dk/page.php';
        $redirect->setDestination($destination_url, $return_url);

        // receiving
        $_SERVER['HTTP_REFERER'] = $return_url;
        $_SERVER['HTTP_HOST']    = 'example.dk';
        $_SERVER['REQUEST_URI']  = '/page.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $this->assertEquals(1, $redirect->getId());
        $redirect_two = Ilib_Redirect::go($this->session_id, $this->db);
        $return_url_two      = 'http://example.dk/page.php';
        $destination_url_two = 'http://example.dk/add_page.php';
        $url = $redirect_two->setDestination($destination_url_two, $return_url_two.'?'.$redirect->getRedirectQueryString());
        $this->assertEquals($destination_url_two.'?redirect_id=2', $url);
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);

        // second recieve
        $_SERVER['HTTP_REFERER'] = $return_url_two;
        $_SERVER['HTTP_HOST']    = 'example.dk';
        $_SERVER['REQUEST_URI']  = '/add_page.php';
        $_GET['redirect_id']     = 2;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $default = 'http://example.dk/another_page.php';
        $this->assertEquals($return_url_two.'?redirect_id=1&return_redirect_id=2', $redirect->getRedirect($default));
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);
    }

    function testLoadRedirectAfterSecondRedirectAndSubmit() {
        // go
        $redirect = Ilib_Redirect::go($this->session_id, $this->db);
        $url1     = 'http://example.dk/state.php?id=1';
        $url2     = 'http://example.dk/page.php';
        $redirect->setDestination($url2, $url1);

        // receiving
        $_SERVER['HTTP_REFERER'] = $url1;
        $_SERVER['HTTP_HOST']    = 'example.dk';
        $_SERVER['REQUEST_URI']  = '/page.php';
        $_GET['redirect_id']     = 1;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $redirect_two = Ilib_Redirect::go($this->session_id, $this->db);
        $url3 = 'http://example.dk/add_page.php';
        $redirect_two->setDestination($url3, $url2.'?'.$redirect->getRedirectQueryString());
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);

        // second recieve
        $_SERVER['HTTP_REFERER'] = $url3;
        $_SERVER['HTTP_HOST']    = 'example.dk';
        $_SERVER['REQUEST_URI']  = '/add_page.php';
        $_GET['redirect_id']     = 2;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $default = 'http://example.dk/another_page.php';
        $this->assertEquals($url2.'?redirect_id=1&return_redirect_id=2', $redirect->getRedirect($default));
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);

        // receiving on first page again
        $_SERVER['HTTP_REFERER'] = $url3;
        $_SERVER['HTTP_HOST']    = 'example.dk';
        $_SERVER['REQUEST_URI']  = '/page.php';
        $_GET['redirect_id']     = 1;
        $_GET['return_redirect_id']     = 2;
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
        unset($_GET['redirect_id']);
        unset($_GET['return_redirect_id']);

        // return after submit
        $_SERVER['HTTP_REFERER'] = $url2;
        $_SERVER['HTTP_HOST']    = 'example.dk';
        $_SERVER['REQUEST_URI']  = '/page.php';
        $redirect = Ilib_Redirect::receive($this->session_id, $this->db);
        $this->assertEquals(1, $redirect->getId());
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REQUEST_URI']);
    }

    function testGetCancelUrlReturnsTrue()
    {
        $redirect = $this->createRedirect();
        $url1     = 'http://example.dk/state.php?id=1';
        $url2     = 'http://example.dk/page.php';
        $url3     = 'http://example.dk/';
        $url4     = 'none';
        $redirect->setDestination($url2, $url1, $url3);
        $this->assertEquals($url3, $redirect->getCancelUrl($url4));

    }

    ////////////////////////////////////////////////////////////////////

    function createRedirect()
    {
        return new Ilib_Redirect($this->session_id, $this->db);
    }

    function getVarsFromUrl($url) {

        $parts = explode('?');
        if(!isset($parts[1])) {
            return array();
        }
        $params = explode('&', $parts[1]);
        if(!is_array($params)) {
            return array();
        }

        $param = array();
        foreach($params AS $p) {
            $parts = explode('=', $p);
            if(is_array($parts) && count($parts) == 2) {
                $param[$parts[0]] = $parts[1];
            }
        }
        return $param;

    }

}
?>
