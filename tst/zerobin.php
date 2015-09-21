<?php
class zerobinTest extends PHPUnit_Framework_TestCase
{
    private $_conf;

    private $_model;

    public function setUp()
    {
        /* Setup Routine */
        $this->_model = zerobin_data::getInstance(array('dir' => PATH . 'data'));
        serversalt::setPath(PATH . 'data');
        $this->_conf = PATH . 'cfg' . DIRECTORY_SEPARATOR . 'conf.ini';
        $this->reset();
    }

    public function tearDown()
    {
        /* Tear Down Routine */
    }

    public function reset()
    {
        $_POST = array();
        $_GET = array();
        $_SERVER = array();
        if ($this->_model->exists(helper::getPasteId()))
            $this->_model->delete(helper::getPasteId());
        if (is_file($this->_conf . '.bak'))
            rename($this->_conf . '.bak', $this->_conf);
    }

    /**
     * @runInSeparateProcess
     */
    public function testView()
    {
        $this->reset();
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $this->assertTag(
            array(
                'tag' => 'title',
                'content' => 'ZeroBin'
            ),
            $content,
            'outputs title correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testViewLanguageSelection()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['main']['languageselection'] = true;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_COOKIE['lang'] = 'de';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $this->assertTag(
            array(
                'tag' => 'title',
                'content' => 'ZeroBin'
            ),
            $content,
            'outputs title correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testHtaccess()
    {
        $this->reset();
        $dirs = array('cfg', 'lib');
        foreach ($dirs as $dir) {
            $file = PATH . $dir . DIRECTORY_SEPARATOR . '.htaccess';
            @unlink($file);
        }
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        foreach ($dirs as $dir) {
            $file = PATH . $dir . DIRECTORY_SEPARATOR . '.htaccess';
            $this->assertFileExists(
                $file,
                "$dir htaccess recreated"
            );
        }
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 2
     */
    public function testConf()
    {
        $this->reset();
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        file_put_contents($this->_conf, '');
        ob_start();
        new zerobin;
        $content = ob_get_contents();
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreate()
    {
        $this->reset();
        $_POST = helper::getPaste();
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(0, $response['status'], 'outputs status');
        $this->assertEquals(
            hash_hmac('sha1', $response['id'], serversalt::get()),
            $response['deletetoken'],
            'outputs valid delete token'
        );
        $this->assertTrue($this->_model->exists($response['id']), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateInvalidTimelimit()
    {
        $this->reset();
        $_POST = helper::getPaste();
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(1, $response['status'], 'outputs error status');
        $this->assertFalse($this->_model->exists(helper::getPasteId()), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateInvalidSize()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['main']['sizelimit'] = 10;
        $options['traffic']['limit'] = 0;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_POST = helper::getPaste();
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(1, $response['status'], 'outputs error status');
        $this->assertFalse($this->_model->exists(helper::getPasteId()), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateProxyHeader()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['header'] = 'X_FORWARDED_FOR';
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_POST = helper::getPaste();
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(1, $response['status'], 'outputs error status');
        $this->assertFalse($this->_model->exists(helper::getPasteId()), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateDuplicateId()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['limit'] = 0;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $this->_model->create(helper::getPasteId(), helper::getPaste());
        $_POST = helper::getPaste();
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(1, $response['status'], 'outputs error status');
        $this->assertTrue($this->_model->exists(helper::getPasteId()), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateValidExpire()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['limit'] = 0;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_POST = helper::getPaste();
        $_POST['expire'] = '5min';
        $_POST['formatter'] = 'foo';
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(0, $response['status'], 'outputs status');
        $this->assertEquals(
            hash_hmac('sha1', $response['id'], serversalt::get()),
            $response['deletetoken'],
            'outputs valid delete token'
        );
        $this->assertTrue($this->_model->exists($response['id']), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateInvalidExpire()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['limit'] = 0;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_POST = helper::getPaste();
        $_POST['expire'] = 'foo';
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(0, $response['status'], 'outputs status');
        $this->assertEquals(
            hash_hmac('sha1', $response['id'], serversalt::get()),
            $response['deletetoken'],
            'outputs valid delete token'
        );
        $this->assertTrue($this->_model->exists($response['id']), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateInvalidBurn()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['limit'] = 0;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_POST = helper::getPaste();
        $_POST['burnafterreading'] = 'neither 1 nor 0';
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(1, $response['status'], 'outputs error status');
        $this->assertFalse($this->_model->exists(helper::getPasteId()), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateInvalidOpenDiscussion()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['limit'] = 0;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_POST = helper::getPaste();
        $_POST['opendiscussion'] = 'neither 1 nor 0';
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(1, $response['status'], 'outputs error status');
        $this->assertFalse($this->_model->exists(helper::getPasteId()), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateAttachment()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['limit'] = 0;
        $options['main']['fileupload'] = true;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_POST = helper::getPaste();
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(0, $response['status'], 'outputs status');
        $this->assertEquals(
            hash_hmac('sha1', $response['id'], serversalt::get()),
            $response['deletetoken'],
            'outputs valid delete token'
        );
        $this->assertTrue($this->_model->exists($response['id']), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateValidNick()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['limit'] = 0;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_POST = helper::getPaste();
        $_POST['nickname'] = helper::getComment()['meta']['nickname'];
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(0, $response['status'], 'outputs status');
        $this->assertEquals(
            hash_hmac('sha1', $response['id'], serversalt::get()),
            $response['deletetoken'],
            'outputs valid delete token'
        );
        $this->assertTrue($this->_model->exists($response['id']), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateInvalidNick()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['limit'] = 0;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_POST = helper::getPaste();
        $_POST['nickname'] = 'foo';
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(1, $response['status'], 'outputs error status');
        $this->assertFalse($this->_model->exists(helper::getPasteId()), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateComment()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['limit'] = 0;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_POST = helper::getComment();
        $_POST['pasteid'] = helper::getPasteId();
        $_POST['parentid'] = helper::getPasteId();
        $_SERVER['REMOTE_ADDR'] = '::1';
        $this->_model->create(helper::getPasteId(), helper::getPaste());
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(0, $response['status'], 'outputs status');
        $this->assertTrue($this->_model->existsComment(helper::getPasteId(), helper::getPasteId(), $response['id']), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateInvalidComment()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['limit'] = 0;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_POST = helper::getComment();
        $_POST['pasteid'] = helper::getPasteId();
        $_POST['parentid'] = 'foo';
        $_SERVER['REMOTE_ADDR'] = '::1';
        $this->_model->create(helper::getPasteId(), helper::getPaste());
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(1, $response['status'], 'outputs error status');
        $this->assertFalse($this->_model->existsComment(helper::getPasteId(), helper::getPasteId(), helper::getCommentId()), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateCommentDiscussionDisabled()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['limit'] = 0;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_POST = helper::getComment();
        $_POST['pasteid'] = helper::getPasteId();
        $_POST['parentid'] = helper::getPasteId();
        $_SERVER['REMOTE_ADDR'] = '::1';
        $paste = helper::getPaste(array('opendiscussion' => false));
        $this->_model->create(helper::getPasteId(), $paste);
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(1, $response['status'], 'outputs error status');
        $this->assertFalse($this->_model->existsComment(helper::getPasteId(), helper::getPasteId(), helper::getCommentId()), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateCommentInvalidPaste()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['limit'] = 0;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $_POST = helper::getComment();
        $_POST['pasteid'] = helper::getPasteId();
        $_POST['parentid'] = helper::getPasteId();
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(1, $response['status'], 'outputs error status');
        $this->assertFalse($this->_model->existsComment(helper::getPasteId(), helper::getPasteId(), helper::getCommentId()), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateDuplicateComment()
    {
        $this->reset();
        $options = parse_ini_file($this->_conf, true);
        $options['traffic']['limit'] = 0;
        if (!is_file($this->_conf . '.bak') && is_file($this->_conf))
            rename($this->_conf, $this->_conf . '.bak');
        helper::createIniFile($this->_conf, $options);
        $this->_model->create(helper::getPasteId(), helper::getPaste());
        $this->_model->createComment(helper::getPasteId(), helper::getPasteId(), helper::getCommentId(), helper::getComment());
        $this->assertTrue($this->_model->existsComment(helper::getPasteId(), helper::getPasteId(), helper::getCommentId()), 'comment exists before posting data');
        $_POST = helper::getComment();
        $_POST['pasteid'] = helper::getPasteId();
        $_POST['parentid'] = helper::getPasteId();
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(1, $response['status'], 'outputs error status');
        $this->assertTrue($this->_model->existsComment(helper::getPasteId(), helper::getPasteId(), helper::getCommentId()), 'paste exists after posting data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testRead()
    {
        $this->reset();
        $this->_model->create(helper::getPasteId(), helper::getPaste());
        $_SERVER['QUERY_STRING'] = helper::getPasteId();
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $this->assertTag(
            array(
                'id' => 'cipherdata',
                'content' => htmlspecialchars(json_encode(helper::getPaste()), ENT_NOQUOTES)
            ),
            $content,
            'outputs data correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testReadInvalidId()
    {
        $this->reset();
        $_SERVER['QUERY_STRING'] = 'foo';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $this->assertTag(
            array(
                'id' => 'errormessage',
                'content' => 'Invalid paste ID'
            ),
            $content,
            'outputs error correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testReadNonexisting()
    {
        $this->reset();
        $_SERVER['QUERY_STRING'] = helper::getPasteId();
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $this->assertTag(
            array(
                'id' => 'errormessage',
                'content' => 'Paste does not exist'
            ),
            $content,
            'outputs error correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testReadExpired()
    {
        $this->reset();
        $expiredPaste = helper::getPaste(array('expire_date' => 1344803344));
        $this->_model->create(helper::getPasteId(), $expiredPaste);
        $_SERVER['QUERY_STRING'] = helper::getPasteId();
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $this->assertTag(
            array(
                'id' => 'errormessage',
                'content' => 'Paste does not exist'
            ),
            $content,
            'outputs error correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testReadBurn()
    {
        $this->reset();
        $burnPaste = helper::getPaste(array('burnafterreading' => true));
        $this->_model->create(helper::getPasteId(), $burnPaste);
        $_SERVER['QUERY_STRING'] = helper::getPasteId();
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $this->assertTag(
            array(
                'id' => 'cipherdata',
                'content' => htmlspecialchars(json_encode($burnPaste), ENT_NOQUOTES)
            ),
            $content,
            'outputs data correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testReadJson()
    {
        $this->reset();
        $this->_model->create(helper::getPasteId(), helper::getPaste());
        $_SERVER['QUERY_STRING'] = helper::getPasteId() . '&json';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(0, $response['status'], 'outputs success status');
        $this->assertEquals(array(helper::getPaste()), $response['messages'], 'outputs data correctly');
    }

    /**
     * @runInSeparateProcess
     */
    public function testReadInvalidJson()
    {
        $this->reset();
        $_SERVER['QUERY_STRING'] = helper::getPasteId() . '&json';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(1, $response['status'], 'outputs error status');
    }

    /**
     * @runInSeparateProcess
     */
    public function testReadOldSyntax()
    {
        $this->reset();
        $oldPaste = helper::getPaste(array('syntaxcoloring' => true));
        unset($oldPaste['meta']['formatter']);
        $this->_model->create(helper::getPasteId(), $oldPaste);
        $_SERVER['QUERY_STRING'] = helper::getPasteId();
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $oldPaste['meta']['formatter'] = 'syntaxhighlighting';
        $this->assertTag(
            array(
                'id' => 'cipherdata',
                'content' => htmlspecialchars(json_encode($oldPaste), ENT_NOQUOTES)
            ),
            $content,
            'outputs data correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testReadOldFormat()
    {
        $this->reset();
        $oldPaste = helper::getPaste();
        unset($oldPaste['meta']['formatter']);
        $this->_model->create(helper::getPasteId(), $oldPaste);
        $_SERVER['QUERY_STRING'] = helper::getPasteId();
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $oldPaste['meta']['formatter'] = 'plaintext';
        $this->assertTag(
            array(
                'id' => 'cipherdata',
                'content' => htmlspecialchars(json_encode($oldPaste), ENT_NOQUOTES)
            ),
            $content,
            'outputs data correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testDelete()
    {
        $this->reset();
        $this->_model->create(helper::getPasteId(), helper::getPaste());
        $this->assertTrue($this->_model->exists(helper::getPasteId()), 'paste exists before deleting data');
        $_GET['pasteid'] = helper::getPasteId();
        $_GET['deletetoken'] = hash_hmac('sha1', helper::getPasteId(), serversalt::get());
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $this->assertTag(
            array(
                'id' => 'status',
                'content' => 'Paste was properly deleted'
            ),
            $content,
            'outputs deleted status correctly'
        );
        $this->assertFalse($this->_model->exists(helper::getPasteId()), 'paste successfully deleted');
    }

    /**
     * @runInSeparateProcess
     */
    public function testDeleteInvalidId()
    {
        $this->reset();
        $this->_model->create(helper::getPasteId(), helper::getPaste());
        $_GET['pasteid'] = 'foo';
        $_GET['deletetoken'] = 'bar';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $this->assertTag(
            array(
                'id' => 'errormessage',
                'content' => 'Invalid paste ID'
            ),
            $content,
            'outputs delete error correctly'
        );
        $this->assertTrue($this->_model->exists(helper::getPasteId()), 'paste exists after failing to delete data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testDeleteInexistantId()
    {
        $this->reset();
        $_GET['pasteid'] = helper::getPasteId();
        $_GET['deletetoken'] = 'bar';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $this->assertTag(
            array(
                'id' => 'errormessage',
                'content' => 'Paste does not exist'
            ),
            $content,
            'outputs delete error correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testDeleteInvalidToken()
    {
        $this->reset();
        $this->_model->create(helper::getPasteId(), helper::getPaste());
        $_GET['pasteid'] = helper::getPasteId();
        $_GET['deletetoken'] = 'bar';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $this->assertTag(
            array(
                'id' => 'errormessage',
                'content' => 'Wrong deletion token'
            ),
            $content,
            'outputs delete error correctly'
        );
        $this->assertTrue($this->_model->exists(helper::getPasteId()), 'paste exists after failing to delete data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testDeleteBurnAfterReading()
    {
        $this->reset();
        $burnPaste = helper::getPaste(array('burnafterreading' => true));
        $this->_model->create(helper::getPasteId(), $burnPaste);
        $this->assertTrue($this->_model->exists(helper::getPasteId()), 'paste exists before deleting data');
        $_GET['pasteid'] = helper::getPasteId();
        $_GET['deletetoken'] = 'burnafterreading';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(0, $response['status'], 'outputs status');
        $this->assertFalse($this->_model->exists(helper::getPasteId()), 'paste successfully deleted');
    }

    /**
     * @runInSeparateProcess
     */
    public function testDeleteInvalidBurnAfterReading()
    {
        $this->reset();
        $this->_model->create(helper::getPasteId(), helper::getPaste());
        $this->assertTrue($this->_model->exists(helper::getPasteId()), 'paste exists before deleting data');
        $_GET['pasteid'] = helper::getPasteId();
        $_GET['deletetoken'] = 'burnafterreading';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $response = json_decode($content, true);
        $this->assertEquals(1, $response['status'], 'outputs status');
        $this->assertTrue($this->_model->exists(helper::getPasteId()), 'paste successfully deleted');
    }

    /**
     * @runInSeparateProcess
     */
    public function testDeleteExpired()
    {
        $this->reset();
        $expiredPaste = helper::getPaste(array('expire_date' => 1000));
        $this->assertFalse($this->_model->exists(helper::getPasteId()), 'paste does not exist before being created');
        $this->_model->create(helper::getPasteId(), $expiredPaste);
        $this->assertTrue($this->_model->exists(helper::getPasteId()), 'paste exists before deleting data');
        $_GET['pasteid'] = helper::getPasteId();
        $_GET['deletetoken'] = 'does not matter in this context, but has to be set';
        ob_start();
        new zerobin;
        $content = ob_get_contents();
        $this->assertTag(
            array(
                'id' => 'errormessage',
                'content' => 'Paste does not exist'
            ),
            $content,
            'outputs error correctly'
        );
        $this->assertFalse($this->_model->exists(helper::getPasteId()), 'paste successfully deleted');
    }
}