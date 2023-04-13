<?php

namespace Home\Controller;

use Think\Controller;

class IndexController extends Controller
{
    /*
    if ($BulletinBoardMode == 0)//文件公告，不处理
    if ($BulletinBoardMode == 1)//数据库公告，检查公告条数，等于0则设置不弹出公告
    if ($BulletinBoardMode == 2)//无公告，设置为不弹出公告
    */
    private $BulletinBoardMode;
    private $BulletinShow;
    private $BulletinDB;

    private function DeConvText($text)
    {
        //将输出内容从UTF8转为本地编码
        if (C('SERVER_CHARSET') == 'zh-cn') {
            return iconv('UTF-8', 'GB2312//IGNORE', $text);
        } else if (C('SERVER_CHARSET') == 'zh-tw') {
            return iconv('UTF-8', 'BIG8//IGNORE', $text);
        } else {
            return $text;
        }
    }

    private function ConvText($text)
    {
        //将输出内容转为UTF8
        if (C('SERVER_CHARSET') == 'zh-cn') {
            return iconv('GB2312', 'UTF-8//IGNORE', $text);
        } else if (C('SERVER_CHARSET') == 'zh-tw') {
            return iconv('BIG8', 'UTF-8//IGNORE', $text);
        } else {
            return $text;
        }
    }

    private function initbulletin()
    {
        $this->BulletinBoardMode = getfarmvalue('BulletinBoardMode'); //从farm读BulletinBoardMode， = 0
        if ($this->BulletinBoardMode == 1) //数据库公告：读出来
        {
            $mbullet = M('bulletinboard');

            //SELECT * FROM bulletinboard WHERE UserID='usr00000000' AND ReleaseTime < NOW() AND (ExpiredTime < '1900-1-1' or ExpiredTime > NOW())
            if ($_SESSION['LonginSucceed']) {
                $this->BulletinDB = $mbullet->where("UserID='1' AND ReleaseTime < NOW() AND (ExpiredTime IS NULL or ExpiredTime > NOW())")->order("ID desc")->limit('10')->select();
            } else {
                $this->BulletinDB = $mbullet->where("UserID='0' AND ReleaseTime < NOW() AND (ExpiredTime IS NULL or ExpiredTime > NOW())")->order("ID desc")->limit('10')->select();
            }

            foreach ($this->BulletinDB as $idx => $bullet) {
                //将输出内容转为UTF8
                $this->BulletinDB[$idx]['title'] = $this->ConvText($bullet['title']);
                $this->BulletinDB[$idx]['content'] = $this->ConvText($bullet['content']);
            }

            $this->assign('BulletinDB', $this->BulletinDB);
        }
        $this->assign('BulletinBoardMode', $this->BulletinBoardMode);
    }

    function __construct()
    {
        parent::__construct();

        if (cookie('think_language') == "")
            cookie('think_language', C('SERVER_CHARSET'));

        try {
            $this->initbulletin();
        } catch (\Exception $e) {
            //数据库连接失败
            $txtResult .= "result=1\r\n";
            $txtResult .= "MsgID=104\r\n";
            $txtResult .= "MsgDesc=" . L('Msg_104');
            echo $txtResult;
            $this->redirect('dberror');
        }

        if (!$_SESSION["RegInfo"]) //获取的信息没有过期，不重新获取
        {
            $_SESSION["RegInfo"] = RegInfo();
            $_SESSION['RegInfo'] = $this->ConvText($_SESSION['RegInfo']);
        }
        if (!empty($_SESSION['UserInfo']['modify_pwd'])) {
            $this->assign('modify_pwd', $_SESSION['UserInfo']['modify_pwd']);
        }
        if ($_SESSION['LonginSucceed']) {
            $this->assign('LonginSucceed', $_SESSION['LonginSucceed']);
        }
    }

    //设置语言：简体/繁体/英文
    public function setlang()
    {
        if (isset($_REQUEST['lang'])) {
            cookie('think_language', $_REQUEST['lang']);
            $this->ajaxReturn(0, '语言更新成功!', 1);
        } else {
            $this->ajaxReturn(0, '语言更新失败!', 0);
        }
        //ajaxReturn($result,$message='',$status=0,$type='')
    }

    //根据id，获取指定的消息文本
    public function gettextmsg()
    {
        $this->ajaxReturn(0, L("Msg_{$_REQUEST['msgid']}"), 1);
    }

    //获取验证码
    public function getverify()
    {
        $v = new \Think\Verify(array('fontSize' => 14, 'useCurve' => false, 'useNoise' => false, 'imageH' => 25, 'imageW' => 100, 'length' => 5));
        $v->entry('login'); //$type);
    }

    function openpath($path = ".", $exten = '*', $ifchild = false)
    {
        $array = array();
        static $file_array = array(); //存放文件名数组
        static $path_array = array(); //存放路径数组(不包括文件名)
        $path = preg_replace('/(.*)([^\/])$/', '$1$2/', $path);
        if (is_dir($path)) {  //检查文件目录是否存在
            $H = @ opendir($path);
            while (false !== ($_file = readdir($H))) {
                //检索目录
                if (is_dir($path . $_file) && $_file != "." && $_file != ".." && $_file !== "Thumbs.db") {
                    if ($ifchild) {
                        openpath($path . $_file, $exten, $ifchild);
                    }
                    //检索文件
                } elseif (is_file($path . $_file) && $_file != "." && $_file != ".." && $_file !== "Thumbs.db") {
                    //$_file = auto_charset($_file,'utf-8','gbk');
                    if ($exten == '*') {
                        array_push($file_array, $_file);
                        array_push($path_array, $path);
                    } else {
                        if (preg_match('/(.*)' . $exten . '/', '/' . $_file . '/')) {
                            array_push($file_array, $_file);
                            array_push($path_array, $path);
                        }
                    }
                }
            }
            closedir($H);
        }
        $array['name'] = $file_array;
        $array['path'] = $path_array;
        return $array;
    }

    private function checklogin()
    {
//        if (!array_key_exists('LonginSucceed', $_SESSION)) //if (!$_SESSION['LonginSucceed'])
//        $mUser = M('cuser');
//        $cuser = $mUser->where("name='{$UName}' and is_group=0 AND is_admin !=1")->find();
        $mUser = M('cuser');
        $sessionpath = session_save_path();
        $pathName = $this->openpath($sessionpath);
        $path = $_REQUEST['sessId'] ? ($sessionpath . "/sess_" . $_REQUEST['sessId']) : ($sessionpath . "/" . $pathName['name'][0]);

        $content = file_get_contents($path);
        $tmp = explode("|", $content);
        $tmp3 = unserialize($tmp[3]);
        $userId = $tmp3['user_id'];
        $cuser = $mUser->where("user_id='{$userId}' AND is_group=0 AND is_admin !=1")->find();
        if (sizeof($cuser) > 0) {
            $_SESSION['UserInfo'] = $cuser;
            $_SESSION['sessId'] = $_REQUEST['sessId'];
            $this->assign('sessId', $_REQUEST['sessId']);
            return true;
        }
        if ($_SESSION['LonginSucceed'] == false) { //尚未登录
            $this->login(); //$this->redirect('index');
            return false;
        } else {
            return true;
        }
    }

    private function checkMustRepwd()
    {
        if ($_SESSION['MustRePWD'] == true) {
            $this->repwd(); //$this->redirect('repwd');
            return false;
        } else {
            return true;
        }
    }


    public function index()
    {
        if (!$this->checklogin()) return;
        if (!$this->checkMustRepwd()) return;
        $parent_id = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : 'null';
        if ($parent_id == '') $parent_id = 'null';
        $mApp = M('application');
        /*
        if ($parent_id == 'null')
          $applist = $mApp->where("(is_dir=1) or (Enabled=1 and (Droit_list like\"%<{$_SESSION['UserInfo']['user_id']}>%\") and f_id = '{$parent_id}')")->order(" is_dir DESC")->select();
        else
          $applist = $mApp->where("Enabled=1 and (Droit_list like\"%<{$_SESSION['UserInfo']['user_id']}>%\") and f_id = '{$parent_id}' and is_dir=0")->select();
        */
        if ($parent_id == 'null') {
//            $txtWhere = "(application.is_dir=1) or (application.enabled=1 and application.f_id='{$parent_id}' and cuser_application.user_id = '{$_SESSION['UserInfo']['user_id']}' AND cuser_application.audited = 1)";
            $txtWhere = "(application.is_dir=1) or (application.enabled=1 and application.f_id='{$parent_id}' and application.droit_list LIKE '%<{$_SESSION['UserInfo']['user_id'] }>%' )";
//          $txtWhere = "(application.is_dir=1) or (application.enabled=1 and application.app_type_mode<>'3' and application.f_id='{$parent_id}' and cuser_application.user_id = '{$_SESSION['UserInfo']['user_id']}' AND cuser_application.audited = 1)";
        } else {
//            $txtWhere = "application.is_dir=0 and application.enabled=1 and application.app_type_mode<>'3' and application.f_id='{$parent_id}' and application.user_id = <'{$_SESSION['UserInfo']['user_id']}'> AND cuser_application.audited = 1";
            $txtWhere = "application.is_dir=0 and application.enabled=1 and application.app_type_mode<>'3' and application.f_id='{$parent_id}' and application.droit_list LIKE '%<{$_SESSION['UserInfo']['user_id'] }>%' ";
        }
//        $applist = $mApp->field("application.app_id, application.name, application.is_dir, application.description, application.app_type_mode, application.ico")->join("cuser_application ON cuser_application.app_id = application.app_id", "left")->where($txtWhere)->order("application.is_dir DESC")->select();
        $applist = $mApp->field("application.app_id, application.name, application.is_dir, application.description, application.app_type_mode, application.ico")->where($txtWhere)->order("application.app_id ASC")->select();
//         echo 'parent_id='.$parent_id.M('application')->_sql();
//        testLog($_SESSION);        AdminUserInfo

        //die;
        if (!empty($applist)) {
            foreach ($applist as $idx => $app) {
                $applist[$idx]['name'] = $this->ConvText($applist[$idx]['name']);
                $applist[$idx]['description'] = $this->ConvText($applist[$idx]['description']);
                if (isset($app['app_id'])) {
                    $imgFilePath = "Public/icon/{$app['app_id']}.png";
                    if ($app['app_type_mode'] == 0) {// C/S
                        $applist[$idx]['webimg'] = $app['ico'] ? "data:image/png;base64," . base64_encode($app['ico']) : "Public/icon/cs.png";
                    } else if ($app['app_type_mode'] == 1) {// desktop
                        $applist[$idx]['webimg'] = file_exists($imgFilePath) ? $imgFilePath : "Public/icon/desktop.png";
                    } else if ($app['app_type_mode'] == 2) {//  content
                        $applist[$idx]['webimg'] = file_exists($imgFilePath) ? $imgFilePath : "Public/icon/content.png";
                    } else if ($app['app_type_mode'] == 3) {//  B/S
                        $applist[$idx]['webimg'] = file_exists($imgFilePath) ? $imgFilePath : "Public/icon/BS.png";
                    } else if ($app['app_type_mode'] == 5) {//  document
                        $applist[$idx]['webimg'] = file_exists($imgFilePath) ? $imgFilePath : "Public/icon/document.png";
                    }
                }
                if ($_SERVER['HTTPS'] == "on")
                    $urlrap = "https://" . $_SERVER['HTTP_HOST'];
                else
                    $urlrap = "http://" . $_SERVER['HTTP_HOST'];
                $myAuthType=5;
                if ($myAuthType == 5) //Windows
                {
                    $InfoLogin['UserInfo']=$_SESSION['UserInfo'];
                    $urlrap .= "/index.php?s=/Agent/GetApp/lang//User/{$InfoLogin['UserInfo']['name']}/PWD/{$InfoLogin['UserInfo']['pwd']}/UserAuthtype/5";
                }
                $VirtualDiskServer = checkExtranetIP($urlrap) ? checkExtranetIP($urlrap) : getfarmvalue("VirtualDiskServer");
                $VirtualDiskServerPort = getfarmvalue("VirtualDiskServerPort");
                $urlrap .= "/vd/1/vds/{$VirtualDiskServer}/vdsp/{$VirtualDiskServerPort}";
                if ($applist[$idx]['is_dir'] == 0) { //文件
                    $_SESSION['RAP_Base_url'] = $_SESSION['RAP_Base_url'] ? $_SESSION['RAP_Base_url'] : $urlrap;
//                    testLog("{$_SESSION['RAP_Base_url']}/AppID/{$app['app_id']}");
                    $applist[$idx]['url'] = "RAP://" . bin2hex(base64_encode(acrypt("{$_SESSION['RAP_Base_url']}/AppID/{$app['app_id']}")));
                } else { //目录
                    $applist[$idx]['url'] = U("Index/applist/pid/{$applist[$idx]['app_id']}");
                }
            }
        }

        //BS
        if ($parent_id == 'null') {
            $txtWhere = "(application.is_dir=1) or (application.enabled=1 and application.app_type_mode='3' and application.f_id='{$parent_id}' and cuser_application.user_id = '{$_SESSION['UserInfo']['user_id']}' AND cuser_application.audited = 1)";
        } else {
            $txtWhere = "application.is_dir=0 and application.enabled=1 and application.app_type_mode='3' and application.f_id='{$parent_id}' and cuser_application.user_id = '{$_SESSION['UserInfo']['user_id']}' AND cuser_application.audited = 1";
        }
//        $applistx = $mApp->field("application.app_id, application.name, application.is_dir,application.description")->join("cuser_application ON cuser_application.app_id = application.app_id", "left")->where($txtWhere)->order("application.is_dir DESC")->select();
//        //echo $mApp->getLastSql();die;
//        foreach ($applistx as $idx => $app) {
//            $applistx[$idx]['name'] = $this->ConvText($applistx[$idx]['name']);
//            $applistx[$idx]['description'] = $this->ConvText($applistx[$idx]['description']);
//            $applistx[$idx]['webimg'] = "/Public/icon/{$app['app_id']}.png"; // U("Index/getappicon/id/{$applist[$idx]['app_id']}");
//            if ($applistx[$idx]['is_dir'] == 0) { //文件
//                $applistx[$idx]['url'] = "RAP://" . bin2hex(base64_encode(acrypt("{$_SESSION['RAP_Base_url']}/AppID/{$app['app_id']}")));
//            } else { //目录
//                $applistx[$idx]['url'] = U("Index/applist/pid/{$applistx[$idx]['app_id']}");
//            }
//
//        }
//        $this->assign('applistx', $applistx);

        $this->assign('applist', $applist);
        $this->display('applist');
    }

    public function login()
    {
        $auth_list = getfarmvalue('auth_type');
        if (!$auth_list) $auth_list = '100000';
        for ($x = 0; $x < 6; $x++) {
            if (substr($auth_list, $x, 1) == '1') {
                $authname = "Page_AuthType{$x}";
                $AuthTypeList["{$x}"] = L($authname);
            }
        }
        //使用哪种方式登录：url传入
        if (isset($_REQUEST['authuse'])) {
            cookie('UserAuthtype', $_REQUEST['authuse']);
        }
        //如果没有设置登录方式，默认设为账号密码登录
        if (cookie('UserAuthtype') > '5' || cookie('UserAuthtype') < '0') {
            cookie('UserAuthtype', '0');
        }

        $val = getfarmvalue('UseCheckCode');
        if ($val && $val == 1) {
            $this->assign('verify_code', 1);
        } else {
            $this->assign('verify_code', 0);
        }

        //print(cookie('UserAuthtype'));
        if (cookie('UserAuthtype') == '2') //ikey
        {
            $_SESSION['IKeyRand'] = sprintf("%04x", rand(0, 65535)); //ikey:有digest,IKeyRandom
            $this->assign('IKeyRand', $_SESSION['IKeyRand']);
        } else {
            unset($_SESSION['IKeyRand']);
        }
        $this->assign('AuthTypeList', $AuthTypeList);
        $this->assign('AuthtypeUse', cookie('UserAuthtype'));
//        $this->display('login');
    }

    //0:http://www.casweb.cn.x/index.php?s=/Index/dologin/name/admin/pwd/c4ca4238a0b923820dcc509a6f75849b
    //5:http://www.casweb.cn.x/index.php?s=/Index/dologin/name/lei/pwd/31000000
    public function dologin() //此接口后面改为get方式传参，ajax方式判定
    { //改为Ajax方式，策略不需要在客户端进行判断
        //所有登录方式，都检查验证码
        $val = getfarmvalue('UseCheckCode');
        if ($val && $val == 1) {
            $v = new \Think\Verify;
            if (!$v->check($_REQUEST["code"], 'login')) {
                $this->ajaxReturn(102, L("Msg_102"), 1); //'验证码错误'
                return;
            }
        }
        $_SESSION['MustRePWD'] = false;
        $mUser = M('cuser');
        $_SESSION['UserInfo'] = $cuser = $mUser->where("name='{$_REQUEST['name']}' and is_group=0 AND is_admin !=1")->find();
        if ($cuser['is_admin'] == '1') {
            $this->ajaxReturn(9805, L("Msg_806"), 1);
            return;
        }
        if (is_str_Chinese($_REQUEST['pwd']) && $_REQUEST['pwd']) {
            $this->ajaxReturn(9805, L("Msg_28"), 1);
            return;
        }
        if ($cuser['name'] == $_REQUEST['name'] && $cuser['pwd'] != md5($_REQUEST['pwd'])) {
            $tmp = explode("/", $_SERVER["QUERY_STRING"]);
            if ($cuser['name'] == $tmp[4] && $cuser['pwd'] == md5($tmp[6])) {
                $_REQUEST['pwd'] = $tmp[6];
            } else {
                $tmp = explode("/", urldecode($_SERVER["QUERY_STRING"]));
                if (substr_count($tmp[6], '<') >= 1) {
                    $_REQUEST['pwd'] = $tmp[6];
                } else {
                    $this->ajaxReturn(9805, L("Msg_1"), 1);//密码不正确
                    return;
                }
            }
        }
        if ($cuser['name'] != $_REQUEST['name']) {
            $ccuser = $mUser->where("pwd='" . md5($_REQUEST['pwd']) . "' and is_group=0")->select();
            if (count($ccuser) == '0') {
                $this->ajaxReturn(1, L("Msg_822"), 0);//用户名和密码都不存在
                return;
            } elseif (count($ccuser) >= '1') {
                $this->ajaxReturn(1, L("Msg_823"), 0);//用户名错误
                return;
            }
        }
        //设置了有效区间
        if ($cuser['datelimittype'] == 1 && $cuser['days'] == 0) {
            if (strtotime(date('Y-m-d')) < strtotime($cuser['startdatetime']) || strtotime(date('Y-m-d')) > strtotime($cuser['enddatetime'])) {
                file_put_contents("a.txt", "\r\n---startdatetime==" . $cuser['startdatetime'] . '--enddatetime=' . $cuser['enddatetime'] . '---' . __LINE__ . 'line', FILE_APPEND);
                $this->ajaxReturn(9805, L("Msg_107", array('startdatetime' => $cuser['startdatetime'], 'enddatetime' => $cuser['enddatetime'])), 1);//
            }
        }
        if ($cuser['datelimittype'] == 0 && $cuser['days'] > 0) {
            if (floor((strtotime(date('Y-m-d')) - strtotime($cuser['startdatetime'])) / 86400) > $cuser['days']) {
//                file_put_contents("a.txt","\r\n---startdatetime==".$cuser['startdatetime'].'--enddatetime='.$cuser['enddatetime'].'---'.__LINE__.'line',FILE_APPEND);
                $this->ajaxReturn(9805, L("Msg_108", array('days' => $cuser['days'])), 1);
            }
        }
        //设置永不过期
        if ($cuser['datelimittype'] == 2 && $cuser['days'] == 0) {
        }
        $myAuthType = 5; // cookie('UserAuthtype');
        if ($myAuthType == '1') //ukey
        {
            $InfoLogin = userlogin($myAuthType, $this->DeConvText($_REQUEST['name']), md5($_REQUEST['pwd']), $_REQUEST['digest']); //ukey,有digest
        } else if ($myAuthType == '2') //ikey
        {
            $InfoLogin = userlogin($myAuthType, $this->DeConvText($_REQUEST['name']), md5($_REQUEST['pwd']), $_REQUEST['digest'], $_SESSION['IKeyRand']); //ikey:有digest,IKeyRandom
        } else {
            if ($cuser['pwdpolicyid']) {
                $mPolicy = M("pwdpolicy");
                $_SESSION['PWDPolicy'] = $PWDPolicy = $mPolicy->where("id='{$cuser['pwdpolicyid']}' and Enable=1")->find();
                if ($PWDPolicy) {
                    $this->checkPolicy($_REQUEST['pwd'], $cuser['pwdpolicyid'], $cuser['modify_pwd'], $cuser['lastrepwddate'], 0);
                    $PwdUseDay = floor((strtotime(date('Y-m-d')) - strtotime($cuser['lastrepwddate'])) / 86400);
//                    if (($PWDPolicy['pwdchangeperiods'] - $PwdUseDay < 2) && $PWDPolicy['pwdchangeperiods'] = $PwdUseDay != 0) //提前2天，提醒修改账号
//                    {
//                        if ($cuser['modify_pwd'] == 0) {
//                            $this->ajaxReturn(9805, L("Msg_105"), 1);
//                        } else {
//                            $this->ajaxReturn(9809, L("Msg_14", array('expire' => date("Y-m-d", (strtotime(date('Y-m-d')) + 172800)))), 1);
//                        }
//                    }
                    if ($PWDPolicy['pwdchangeperiods']) {
                        if ($PwdUseDay > $PWDPolicy['pwdchangeperiods']) //密码过期，强制要求修改
                        {
                            if ($cuser['modify_pwd'] == 0) {
                                $this->ajaxReturn(9805, L("Msg_105"), 1);
                            } else {
                                $this->ajaxReturn(9809, L("Msg_15"), 1);
                            }
                        }
                    }
                }
            }

            $InfoLogin = userlogin($myAuthType, $this->DeConvText($_REQUEST['name']), md5($_REQUEST['pwd']), ""); //非ukey，ikey，没有digest
        }
        if ($InfoLogin["LonginSucceed"] != 0) //账号密码正确
        {
            $sessId = session_id();
            $_SESSION['LonginSucceed'] = true;
            $_SESSION['UserInfo'] = $InfoLogin['UserInfo'];
            // $this->assign('LonginSucceed', $_SESSION['LonginSucceed']);
            // if ($InfoLogin['UserInfo']['is_admin'] == 1)
            // {
            //     $this->ajaxReturn(806, L("Msg_806"), 1);
            // }
            /*
            if ($_SERVER['HTTPS'] == "on")
              $urlrap = "https://".$_SERVER['HTTP_HOST']."/index.php?s=/Agent/GetApp/lang/{$this->Language}/User/{$_GET['name']}/PWD/{$_GET['pwd']}/UserAuthtype/{$myAuthType}";
            else
              $urlrap = "http://".$_SERVER['HTTP_HOST']."/index.php?s=/Agent/GetApp/lang/{$this->Language}/User/{$_GET['name']}/PWD/{$_GET['pwd']}/UserAuthtype/{$myAuthType}";
            */

            if ($_SERVER['HTTPS'] == "on")
                $urlrap = "https://" . $_SERVER['HTTP_HOST'];
            else
                $urlrap = "http://" . $_SERVER['HTTP_HOST'];
            $VirtualDiskServer = checkExtranetIP($urlrap) ? checkExtranetIP($urlrap) : getfarmvalue("VirtualDiskServer");
            if ($myAuthType == 5) //Windows
            {
                $urlrap .= "/index.php?s=/Agent/GetApp/lang/{$this->Language}/User/{$InfoLogin['UserInfo']['name']}/PWD/{$InfoLogin['UserInfo']['pwd']}/UserAuthtype/5";
            } elseif ($myAuthType == 1 || $myAuthType == 2) //eKey/iKey
            {
                //去掉: /PWD/{$_GET['PWD']}
                $urlrap .= "/index.php?s=/Agent/GetApp/lang/{$this->Language}/User/{$InfoLogin['UserInfo']['name']}/UserAuthtype/{$myAuthType}";
                if ($_GET['digest']) $urlrap .= "/Digetst/{$_GET['digest']}";
                if ($_SESSION['IKeyRand']) $urlrap .= "/IKeyRand/{$_SESSION['IKeyRand']}";
            } else //其他按照账号密码登录方式生产rap://
            {
                $urlrap .= "/index.php?s=/Agent/GetApp/lang/{$this->Language}/User/{$InfoLogin['UserInfo']['name']}/PWD/{$InfoLogin['UserInfo']['pwd']}/UserAuthtype/0";
            }
//            $VirtualDiskServer = getfarmvalue("VirtualDiskServer");
            $VirtualDiskServerPort = getfarmvalue("VirtualDiskServerPort");
            $urlrap .= "/vd/1/vds/{$VirtualDiskServer}/vdsp/{$VirtualDiskServerPort}";

            $_SESSION['RAP_Base_url'] = $urlrap;
//            $this->ajaxReturn($InfoLogin["ErrorID"], L("Log_1"), $InfoLogin["LonginSucceed"]);//data+info+status
            $this->ajaxReturn($sessId, L("Log_1"), $InfoLogin["LonginSucceed"]);//data+info+status
        }

        if ($InfoLogin["LonginSucceed"] == 3) //密码过期，强制要求修改
        {
            $_SESSION['MustRePWD'] = true;
            $this->ajaxReturn(9801, L("Msg_106", array('leng' => $PWDPolicy['pwdchangeperiods'])), 1);
        }

        if ($InfoLogin["LonginSucceed"] == 0) {
            $this->ajaxReturn($InfoLogin["ErrorID"], $InfoLogin["ErrorText"], $InfoLogin["LonginSucceed"]);
        }
    }

    public function checkPolicy($pwd, $pwdpolicyid, $modify_pwd, $lastrepwddate, $reflag)
    {
        $mPolicy = M("pwdpolicy");
        $PWDPolicy = $mPolicy->where("id='{$pwdpolicyid}' and Enable=1")->find();
        if ($PWDPolicy) {
            $str = $pwd;
            if (mb_strlen($str, 'utf-8') < $PWDPolicy['length'] && $PWDPolicy['length']) {//密码长度小于$PWDPolicy['length']
                if ($modify_pwd == 0) {
                    $this->ajaxReturn(9805, L("Msg_105"), 1);
                    exit;
                } else {
                    $this->ajaxReturn($reflag != 1 ? 9809 : 9808, L("Msg_30"), $reflag != 1 ? 1 : 0);
                    exit;
                }

            }//不包含特殊字符
            if (!preg_match("/[\'.,:;*?~`$!@#%^&+=)(<>{}_-]|\]|\[|\/|\\\|\"|\|/", $str) && $PWDPolicy['includespecialcharacter'] == 1) {
//              if (!preg_match("/[\'.,:;*?~`!@#$%^&+=)(<>{}_-]|\]|\[|\/|\\\|\"|\|/", $str) && $PWDPolicy['includespecialcharacter'] == 1)
                if ($modify_pwd == 0) {
                    $this->ajaxReturn(9805, L("Msg_105"), 1);
                    exit;
                } else {
                    $this->ajaxReturn($reflag != 1 ? 9809 : 9808, L("Msg_30"), $reflag != 1 ? 1 : 0);
                    exit;
                }
            }
            if (is_str_large($str) && $PWDPolicy['capitalletter'] == 1) {//为false，表示有大写字母 echo "不包含大写字母".var_dump(is_str_large($str)).'<br/>';
                if ($modify_pwd == 0) {
                    $this->ajaxReturn(9805, L("Msg_105"), 1);
                    exit;
                } else {
                    $this->ajaxReturn($reflag != 1 ? 9809 : 9808, L("Msg_30"), $reflag != 1 ? 1 : 0);
                    exit;
                }
            }
            if (is_str_sm($str) && $PWDPolicy['smallletter'] == 1) {//为false，表示有小写字母
                // echo "不包含小写字母".var_dump(is_str_large($str)).'<br/>';
                if ($modify_pwd == 0) {
                    $this->ajaxReturn(9805, L("Msg_105"), 1);
                    exit;
                } else {
                    $this->ajaxReturn($reflag != 1 ? 9809 : 9808, L("Msg_30"), $reflag != 1 ? 1 : 0);
                    exit;
                }
            }
            if (!preg_match('/[0-9]+/', $str) && $PWDPolicy['includenumber'] == 1) {
                if ($modify_pwd == 0) {
                    $this->ajaxReturn(9805, L("Msg_105"), 1);
                    exit;
                } else {
                    $this->ajaxReturn($reflag != 1 ? 9809 : 9808, L("Msg_30"), $reflag != 1 ? 1 : 0);//不包含数字
                    exit;
                }
            }
        }
    }

    public function repwd() //显示修改密码页
    {
        if ($_SESSION['LonginSucceed'] || !$_SESSION['PWDPolicy'] || !$_SESSION['UserInfo']['pwdpolicyid'] || $_SESSION['UserInfo']['modify_pwd'] != 1) {
            $_SESSION = array();
            $this->redirect('login');
            return;
        }

        if (cookie('UserAuthtype') == '0' || cookie('UserAuthtype') == '5') {
            //账号密码方式需要客户端校验密码
            //Windows账号方式，由操作系统的密码策略进行校验
            if (cookie('UserAuthtype') == '0') {
                $mUser = M('cuser');
                $cuser = $mUser->field('pwdpolicyid')->where("user_id='{$_SESSION['UserInfo']['user_id']}'")->find();


                if ($cuser['pwdpolicyid']) {
                    $mPolicy = M("pwdpolicy");
                    $_SESSION['PWDPolicy'] = $PWDPolicy = $mPolicy->where("id='{$cuser['pwdpolicyid']}' and Enable=1")->find();
                    //"SELECT RemainDays,PWDChangePeriods,IncludeLetter,CapitalLetter,SmallLetter,IncludeNumber,Length,PWDRepeatDegree,IncludeSpecialCharacter from PWDPolicy where ID='{$PWDPolicyID}' and Enable=1"
                    if ($_SESSION['PWDPolicy']) {
                        $this->assign('PWDPolicy', $_SESSION['PWDPolicy']);
                        $this->assign('length', $PWDPolicy['length']);
                        $this->assign('smallletter', $PWDPolicy['smallletter']);
                        $this->assign('capitalletter', $PWDPolicy['capitalletter']);
                        $this->assign('includenumber', $PWDPolicy['includenumber']);
                        $this->assign('includespecialcharacter', $PWDPolicy['includespecialcharacter']);
                        $this->assign('pwdchangeperiods', $PWDPolicy['pwdchangeperiods']);
                    }
                }
                if ($_SESSION['PWDPolicy']) {
                    $this->assign('PWDPolicy', $_SESSION['PWDPolicy']);
                }
            }
            $this->display("repwd");
        } else {
            $this->redirect('password');
        }
    }

    public function dorepwd() //修改密码
    {
        if (cookie('UserAuthtype') == '0') {
            //$PWDPolicy['pwdrepeatdegree']：记录之前几次的密码，修改密码不能与之重复
            //历史密码记录:$_SESSION['UserInfo']['historypwd']，没有什么用处。。
            //从web修改，只记录，未检查，
            //从控制台修改，未记录，未检查
            //dump($_SESSION['PWDPolicy']);die;
            //传入的新老密码均为md5串，
            //检查原密码是否正确
//            $this->ajaxReturn(300, $_SESSION['UserInfo']['pwd'].L('Msg_404').($_REQUEST['pwd']), 0);
            if ($_SESSION['UserInfo']['pwd'] != $_REQUEST['pwd']) {
                $this->ajaxReturn(9802, L('Msg_404'), 0);
                // 登录框输入用户名查询的密码与修改密码页输入的原密码不相同时的提示
                return;
            }
            //直接写入数据库
            $mUser = M('cuser');

            $tmp = explode("/", urldecode($_SERVER["QUERY_STRING"]));//解决特殊符号<

            if (substr_count($tmp[6], '<') >= 1) {
                $_REQUEST['npwd'] = $tmp[6];

            }
            $data['pwd'] = md5($_REQUEST['npwd']);
            $data['LastREPWDDate'] = date('Y-m-d H:i:s');
            $cuser = $mUser->where("name='{$_SESSION['UserInfo']['name']}' and is_group=0")->find();
            if (is_str_Chinese($_REQUEST['npwd']) && $_REQUEST['npwd']) {
                $this->ajaxReturn(300, L("Msg_28"), 0);//含有中文
                return;
            } elseif (preg_match("/[，。、；‘’、【】《》？：“”——（）……￥！·]/", $_REQUEST['npwd'])) {
                $this->ajaxReturn(300, L("Msg_28"), 0);//含有中文字符
                return;
            }
            if ($cuser['pwdpolicyid']) {
                $mPolicy = M("pwdpolicy");
                $_SESSION['PWDPolicy'] = $PWDPolicy = $mPolicy->where("id='{$cuser['pwdpolicyid']}' and Enable=1")->find();
                if ($PWDPolicy) {
                    $this->checkPolicy($_REQUEST['npwd'], $cuser['pwdpolicyid'], $cuser['modify_pwd'], $cuser['lastrepwddate'], 1);
                }
            }
            if ($_SESSION['PWDPolicy']['pwdrepeatdegree'] > 0) {
                $data['HistoryPWD'] = $_SESSION['UserInfo']['HistoryPWD'] . $_REQUEST['npwd'] . ";";
                $data['HistoryPWD'] = substr($data['HistoryPWD'], -$_SESSION['PWDPolicy']['pwdrepeatdegree'] * 33);
            } else {
                $data['HistoryPWD'] = '';
            }
            if ($mUser->where("user_id='{$_SESSION['UserInfo']['user_id']}'")->save($data) === false) {
                //echo $mUser->getLastSql();
                $this->ajaxReturn(104, L('Msg_104'), 0); //数据库错误
                return;
            } else {
                //echo $mUser->getLastSql();die;
                $_SESSION['UserInfo']['pwd'] = md5($_REQUEST['npwd']);
                $_SESSION['UserInfo']['LastREPWDDate'] = $data['LastREPWDDate'];
                $_SESSION['UserInfo']['HistoryPWD'] = $data['HistoryPWD'];
                $_SESSION['MustRePWD'] = false;
                if ($_REQUEST['flag'] == 1) {
                    $_SESSION = array();
                }
                $this->ajaxReturn(300, L('Msg_300'), 1); //密码更新成功
                return;
            }
        } else if (cookie('UserAuthtype') == '5') {
            //解密
            $pwd = str_replace("00", "", $_REQUEST['pwd']);
            $pwd = hex2bin($pwd);
            $npwd = str_replace("00", "", $_REQUEST['npwd']);
            $npwd = hex2bin($npwd);

            if (getfarmvalue('IsDomain') == "yes") {
                $sDomain = getfarmvalue('DomainName');
                $sLoginAdmin = getfarmvalue('SysAccountName');
                $sLoginAdmin = Crypt2(base64_decode($sLoginAdmin), False);
                $sLoginPwd = getfarmvalue('SysAccountPass');
                $sLoginPwd = Crypt2(base64_decode($sLoginPwd), False);
                if (($sLoginAdmin == '') || ($sLoginPwd == '')) {
                    $this->ajaxReturn(9, L('Msg_9'), 0); //更改密码失败
                    return;
                }

                $cmdContent = "{$sLoginAdmin},{$sLoginPwd},{$_SESSION['UserInfo']['name']},{$pwd},{$npwd},{$sDomain}";
            } else {
                $cmdContent = ",,{$_SESSION['UserInfo']['name']},{$pwd},{$npwd},";
            } //{顺序为：aLgnUserName, aLgnPassWord, aUserName, aOldPassWord,  aNewPassWord, aAddDomain}
            //echo $cmdContent;die;
            $key = rand(0, 65535); //999;//
            $cmdContent = cmnEncryptString(base64_encode(InnerCryptNEW($cmdContent, $key, 10086, 2010)), '6746');

            $strIP = '';
            $strPort = '';
            $ServerId = getfarmvalue("LogServer");
            $mServer = M('server');
            $cServer = $mServer->field('server.banport as banport, serverip.ip as ip, server.actived')
                ->join('INNER JOIN serverip ON serverip.serverid=server.ServerId')
                ->where("server.ServerId='{$ServerId}'")->find();
            if ($cServer) {
                $strPort = $cServer['banport'];
                $strIP = $cServer['ip'];
            }
            if ($strIP == '') {
                $strIP = 'http://127.0.0.1';
            } else {
                $strIP = 'http://' . $strIP;
            }
            if ($strPort == '') {
                $strPort = '5871';
            }

            $CMDURL = "{$strIP}:{$strPort}/BanRequest?&cmd=1012&param={$cmdContent}&key={$key}";
            //echo $CMDURL; //die;

            $ch = curl_init($CMDURL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 获取数据返回
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true); // 在启用 CURLOPT_RETURNTRANSFER 时候将获取数据返回
            $MessageStr = curl_exec($ch);

            //echo '||bb'.$MessageStr.'aa||';
            if ($MessageStr == 'succ') {
                //记录密码更改日期
                $mUser = M('cuser');
                $data['LastREPWDDate'] = date('Y-m-d');
                $mUser->where("user_id='{$_SESSION['UserInfo']['user_id']}'")->save($data);

                $_SESSION['MustRePWD'] = false;
                $this->ajaxReturn(300, L('Msg_300'), 1); //密码更新成功
                return;
            } else {
                $MessageStr = $this->ConvText($MessageStr);
                $this->ajaxReturn(99999, $MessageStr, 0); //密码更新失败
                return;
            }
            /*
            if ( !$this->ChangeNTUserPwd($CMDUrl, $ReturnValue) )
            { //更改nt用户密码失败
              $ErrID = 99999;
              $_SESSION['LgIni']["Msg"][$ErrID] = $ReturnValue;
              $ReturnValue = encoderesult( $ErrID, $ReturnValue );
              return $ReturnValue;
            }
            */
        }
    }

    public function title()
    {
        if (!$this->checklogin()) return;

        $UserName = $this->ConvText($_SESSION['UserInfo']['name']);
        $this->assign('UserName', $UserName);
        $this->display("title");
    }

    //注销
    public function dologout()
    {
//        $_SESSION = array();
        $_SESSION['LonginSucceed'] = false;
        $_SESSION['Is_Admin'] = false;
        $_SESSION['UserInfo'] = array();
        $this->redirect('login');
    }

    //公告栏目：文件公告
    public function bulletin()
    {
        if (!$this->checklogin()) return;
        if (!$this->checkMustRepwd()) return;

        if ($this->BulletinBoardMode == 0) //文件公告
        { //显示默认公告
            if ($_SESSION['LonginSucceed']) {
                //登录后
                $this->display('bulletinlogined');
            } else {
                //登录前
                $this->display('bulletinlogin');
            }
        } elseif ($this->BulletinBoardMode == 1) //数据库公告：读出来
        {
            $this->display('bulletindb');
        }

    }

    //列出app列表：已获得使用权限的应用列表
    //应用列表最多分为2级，f_id为null的是最上一级
    public function applist()
    {
        if (!$this->checklogin()) return;
        if (!$this->checkMustRepwd()) return;

        $parent_id = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : 'null';
        if ($parent_id == '') $parent_id = 'null';

        $mApp = M('application');
        /*
        if ($parent_id == 'null')
          $applist = $mApp->where("(is_dir=1) or (Enabled=1 and (Droit_list like\"%<{$_SESSION['UserInfo']['user_id']}>%\") and f_id = '{$parent_id}')")->order(" is_dir DESC")->select();
        else
          $applist = $mApp->where("Enabled=1 and (Droit_list like\"%<{$_SESSION['UserInfo']['user_id']}>%\") and f_id = '{$parent_id}' and is_dir=0")->select();
        */
        if ($parent_id == 'null')
            $txtWhere = "(application.is_dir=1) or (application.enabled=1 and application.app_type_mode<>'3' and application.f_id='{$parent_id}' and cuser_application.user_id = '{$_SESSION['UserInfo']['user_id']}' AND cuser_application.audited = 1)";
        else
            $txtWhere = "application.is_dir=0 and application.enabled=1 and application.app_type_mode<>'3' and application.f_id='{$parent_id}' and cuser_application.user_id = '{$_SESSION['UserInfo']['user_id']}' AND cuser_application.audited = 1";

        $applist = $mApp->field("application.app_id, application.name, application.is_dir,application.description")->join("cuser_application ON cuser_application.app_id = application.app_id", "left")->where($txtWhere)->order("application.is_dir DESC")->select();
        //echo $mApp->getLastSql();die;
        foreach ($applist as $idx => $app) {
            $applist[$idx]['name'] = $this->ConvText($applist[$idx]['name']);
            $applist[$idx]['description'] = $this->ConvText($applist[$idx]['description']);
            $applist[$idx]['webimg'] = "/Public/icon/{$app['app_id']}.png"; // U("Index/getappicon/id/{$applist[$idx]['app_id']}");
            if ($applist[$idx]['is_dir'] == 0) { //文件
                $applist[$idx]['url'] = "RAP://" . bin2hex(base64_encode(acrypt("{$_SESSION['RAP_Base_url']}/AppID/{$app['app_id']}")));
            } else { //目录
                $applist[$idx]['url'] = U("Index/applist/pid/{$applist[$idx]['app_id']}");
            }
        }

        //BS
        if ($parent_id == 'null')
            $txtWhere = "(application.is_dir=1) or (application.enabled=1 and application.app_type_mode='3' and application.f_id='{$parent_id}' and cuser_application.user_id = '{$_SESSION['UserInfo']['user_id']}' AND cuser_application.audited = 1)";
        else
            $txtWhere = "application.is_dir=0 and application.enabled=1 and application.app_type_mode='3' and application.f_id='{$parent_id}' and cuser_application.user_id = '{$_SESSION['UserInfo']['user_id']}' AND cuser_application.audited = 1";

        $applistx = $mApp->field("application.app_id, application.name, application.is_dir,application.description")->join("cuser_application ON cuser_application.app_id = application.app_id", "left")->where($txtWhere)->order("application.is_dir DESC")->select();
        //echo $mApp->getLastSql();die;
        foreach ($applistx as $idx => $app) {
            $applistx[$idx]['name'] = $this->ConvText($applistx[$idx]['name']);
            $applistx[$idx]['description'] = $this->ConvText($applistx[$idx]['description']);
            $applistx[$idx]['webimg'] = "/Public/icon/{$app['app_id']}.png"; // U("Index/getappicon/id/{$applist[$idx]['app_id']}");
            if ($applistx[$idx]['is_dir'] == 0) { //文件
                $applistx[$idx]['url'] = "RAP://" . bin2hex(base64_encode(acrypt("{$_SESSION['RAP_Base_url']}/AppID/{$app['app_id']}")));
            } else { //目录
                $applistx[$idx]['url'] = U("Index/applist/pid/{$applistx[$idx]['app_id']}");
            }
        }

        $this->assign('applistx', $applistx);
        $this->assign('applist', $applist);
        $this->display('applist');
    }

    //取指定app的图标（bmp）
    public function getappicon()
    {
        if (!$_SESSION['LonginSucceed']) { //未登录
            return;
        }

        $mApp = M("application");
        $app = $mApp->field("bmp")->where("app_id='{$_GET['id']}'")->find();
        if ($app != null) {
            Header("Content-type: image/bmp");
            echo $app["bmp"];
        }
    }

    //生成指定二维码
    public function qrcode()
    {
        $val = getfarmvalue('FarmName');
        if (!$val) {
            echo '请输入需编码的数据';
            return;
        }

        $farmname = $this->ConvText($val);

        $farmweb = $_SERVER['SERVER_NAME'] . ";" . $_SERVER["SERVER_PORT"];
        $data = urlencode(base64_encode($farmname . ';' . $farmweb));

        import("@.ORG.Util.phpqrcode");
        $png_src = \Org\Util\QRcode::png($data, false, QR_ECLEVEL_L, 3, 2);
        //png($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint=false)

        header("Content-type: image/png");
        imagepng($png_src);
    }

    //华北油田相关功能
    //用户自注册
    //http://www.hbyt.com.x/index.php?s=/Index/doregist/name/tt1/fullname/testtt1/pwd/c4ca4238a0b923820dcc509a6f75849b
    public function regist()
    {
        //是否允许用户自注册
        $AllowUserRegist = getfarmvalue('AllowUserRegist');
        if (!$AllowUserRegist) {
            $this->redirect("index");
            return;
        }

        $UserRegistAuthType = 5;
        /*
        //只允许注册"windows账号"或者"账号密码"
        $UserRegistAuthType = getfarmvalue('UserRegistAuthType');
        if ($UserRegistAuthType != 0 && $UserRegistAuthType != 5)
        {
          $this->redirect("index");
          return;
        }
        */

        /*
        //注册Windows账号，只允许域环境
        $IsDomain = getfarmvalue('IsDomain');
        if ($UserRegistAuthType != 5 && $IsDomain == 'no')
        {
          $this->redirect("index");
          return;
        }
        */
        $this->assign('UserRegistAuthType', $UserRegistAuthType);

        //是否允许用户自注册时选择用户组
        $AllowSelectGroup = getfarmvalue('AllowSelectGroup');
        $this->assign('AllowSelectGroup', $AllowSelectGroup);
        if ($AllowSelectGroup == 1) {
            //取所有用户组列表
            $mGroup = M('cuser');
            $listgroup = $mGroup->field("user_id, name")->where("is_group = 1")->select();
            $this->assign('listgroup', $listgroup);
        }

        $val = getfarmvalue('UseCheckCode');
        if ($val && $val == 1) {
            $this->assign('verify_code', true);
        } else {
            $this->assign('verify_code', false);
        }
        $this->display();
    }

    //5:http://www.hbyt.com.x/index.php?s=/Index/doregist/name/lei/pwd/31000000
    //0:http://www.hbyt.com.x/index.php?s=/Index/doregist/name/tt1/fullname/testtt1/pwd/c4ca4238a0b923820dcc509a6f75849b
    public function doregist()
    {
        //所有登录方式，都检查验证码
        $val = getfarmvalue('UseCheckCode');
        if ($val && $val == 1) {
            $v = new \Think\Verify;
            if (!$v->check($_REQUEST["code"], 'login')) {
                $this->ajaxReturn(102, L("Msg_102"), 0); //'验证码错误'
                return;
            }
        }
        /*
        //新增的几个关于用户自注册的设置项
        fmp00000123    AllowUserRegist  0
        fmp00000124    UserRegistAuthType  5  ：0或者5
        fmp00000125    UserRegistAutoAudit  0
        fmp00000126    AllowSelectGroup  1
        fmp00000127    DefaultUserGroup
        */
        $AllowUserRegist = getfarmvalue('AllowUserRegist');
        if (!$AllowUserRegist) {
            $this->ajaxReturn(400, L("Msg_400"), 0); //未开启用户注册
            return;
        }

        $UserRegistAuthType = 5;
        /*
        $UserRegistAuthType = getfarmvalue('UserRegistAuthType');
        if ($UserRegistAuthType != 0 && $UserRegistAuthType != 5)
        {
          $this->ajaxReturn(401, L("Msg_401"), 0); //管理员未设定自注册用户类型
          return;
        }
        */

        $AllowSelectGroup = getfarmvalue('AllowSelectGroup');
        if ($AllowSelectGroup == 1) //允许用户自注册时选择用户组
        {
            $UserGroupID = $_REQUEST['group'];
        } else //默认用户组
        {
            $UserGroupID = getfarmvalue('DefaultUserGroupID');
        }
        //检查$UserGroupID是否存在
        $mGroup = M('cuser');
        $group = $mGroup->field("user_id")->where("user_id = '{$UserGroupID}'")->find();
        if (!$group) {
            $this->ajaxReturn(406, L("Msg_406"), 0); //没有指定用户组
            return;
        }
        /*
            $IsDomain = getfarmvalue('IsDomain');
            if ($UserRegistAuthType != 5 && $IsDomain == 'no')
            {
              $this->ajaxReturn(407, L("Msg_407"), 0); //必须在域环境下，才能注册Windows账号
              return;
            }
        */
        //注册用户
        $Result = UserRegist($UserRegistAuthType, $this->DeConvText($_REQUEST['name']), $this->DeConvText($_REQUEST['fullname']), $_REQUEST['pwd'], $UserGroupID);
        $_SESSION['LonginSucceed'] = false;
        if ($Result['Result']) {
            $this->ajaxReturn($Result['ErrorID'], $Result['ErrorText'], 1); //成功
        } else {
            $this->ajaxReturn($Result['ErrorID'], $Result['ErrorText'], 0); //失败
        }
    }

    //申请应用
    public function apply() //列表没有获得权限、也没有发起申请的应用
    {
        if (!$this->checklogin()) return;
        if (!$this->checkMustRepwd()) return;

        $mApp = M('application');
        $txtWhere = "application.is_dir=0 and application.enabled=1 and u_a.user_id is null";
        $applist = $mApp->field("application.app_id, application.name, application.description")->join("(SELECT * FROM cuser_application WHERE user_id='{$_SESSION['UserInfo']['user_id']}') u_a ON u_a.app_id = application.app_id", "left")->where($txtWhere)->select();
        //echo $mApp->getLastSql();die;
        foreach ($applist as $idx => $app) {
            $applist[$idx]['name'] = $this->ConvText($applist[$idx]['name']);
            $applist[$idx]['description'] = $this->ConvText($applist[$idx]['description']);
            $applist[$idx]['webimg'] = "/Public/icon/{$app['app_id']}.png"; // U("Index/getappicon/id/{$applist[$idx]['app_id']}");
        }

        $this->assign('applist', $applist);
        //dump($applist);
        $this->display('apply');
    }

    public function doapply() //处理请求，添加到应用申请列表
    {
        if (!$this->checklogin()) return;

        if ($_GET['list'] == '') {
            $this->ajaxReturn(8001, L("Msg_8001"), 0); //'请至少选择一项！';
            return;
        }

        $listAppID = explode(',', $_GET['list']); //var_dump($listAppID);
        $listAppName = explode(',', $_GET['name']); //var_dump($listAppName);
        $mUserApp = M('cuser_application');
        try {
            foreach ($listAppID as $idx => $appID) {
                $mUserApp->execute("INSERT INTO cuser_application (user_id,app_id,app_name,time_apply) SELECT '{$_SESSION['UserInfo']['user_id']}', app_id, name, now() FROM application WHERE app_id='{$appID}'");
            }
        } catch (\Exception $e) {
            //数据库连接失败
            $this->ajaxReturn(901, L("Msg_901"), 0);
        }

        //echo $mUserApp->getLastSql();
        $this->ajaxReturn(0, L("Msg_900"), 1);
    }

    //申请中的应用列表：尚未审核通过的
    public function applylist() //列出发起申请，尚未通过的应用
    {
        if (!$this->checklogin()) return;
        if (!$this->checkMustRepwd()) return;

        $mApp = M('cuser_application');
        $applist = $mApp->where("user_id = '{$_SESSION['UserInfo']['user_id']}' AND audited = 0")->select();
        //echo $mApp->getLastSql();die;
        foreach ($applist as $idx => $app) {
            $applist[$idx]['app_name'] = $this->ConvText($app['app_name']);
        }

        $this->assign('applist', $applist);
        //dump($applist);
        $this->display('applylist');
    }

    //取消申请
    public function cancelapply() //取消发起申请，尚未通过的应用
    {
        if (!$this->checklogin()) return;

        $mApply = M('cuser_application');
        $apply = $mApply->where("user_id = '{$_SESSION['UserInfo']['user_id']}' AND audited = 0 AND id = '{$_GET['id']}'")->find();
        if (!$apply) {
            $this->ajaxReturn(903, L("Msg_903"), 0); //该应用申请不存在
            return;
        }

        //写日志
        $data['user_id'] = $apply['user_id'];
        $data['app_id'] = $apply['app_id'];
        $data['app_name'] = $apply['app_name'];
        $data['time_apply'] = $apply['time_apply'];
        $data['time_audit'] = array('exp', 'NOW()');
        $data['result_audit'] = 2; // 0:申请已通过,1:申请被拒绝,2:申请已取消,
        $data['user_id_audit'] = $apply['user_id'];
        $mAuditLog = M('cuser_app_audit_log');
        $mAuditLog->add($data);

        //删除
        $mApply->where("id = {$apply['id']}")->delete();

        $this->ajaxReturn(0, L("Msg_902"), 1);
    }

    //应用申请的日志
    public function auditlog()  //列表应用审核日志
    {
        if (!$this->checklogin()) return;
        if (!$this->checkMustRepwd()) return;

        $mApp = M('cuser_app_audit_log');
        $auditlog = $mApp->where("user_id = '{$_SESSION['UserInfo']['user_id']}'")->order('id desc')->select();

        foreach ($auditlog as $idx => $log) {
            $auditlog[$idx]['app_name'] = $this->ConvText($log['app_name']);
        }

        $this->assign('auditlog', $auditlog);
        //dump($auditlog);
        $this->display('auditlog');
    }

    //应用程序使用日志
    public function applog()
    {
        if (!$this->checklogin()) return;
        if (!$this->checkMustRepwd()) return;

        $conditon = "farmuser='{$_SESSION['UserInfo']['name']}'"; //"farmuser='admin'"; //
        $nowPage = isset($_GET["p"]) ? $_GET["p"] : 1;
        if ($nowPage < 1) $nowPage = 1;
        $pageRows = 30;
        $pageStart = ($nowPage - 1) * $pageRows;

        $mAppLog = M("appshenji", "", "DB_CONFIG_Log");

        $count = $mAppLog->where($conditon)->count();// 查询满足要求的总记录数
        $Page = new \Think\Page($count, $pageRows);// 实例化分页类 传入总记录数和每页显示的记录数
        $showPage = $Page->show();// 分页显示输出
        $this->assign('showPage', $showPage);

        $listLog = $mAppLog->where($conditon)->limit("$pageStart, $pageRows")->order("starttime desc")->select();
        foreach ($listLog as $idx => $log) {
            //将输出内容转为UTF8
            $listLog[$idx]['appshowname'] = $this->ConvText($log['appshowname']);
        }

        $this->assign('listLog', $listLog);
        $this->display();
    }

    public function about()
    {
        $this->checklogin();
        $this->display();
    }

    public function download()
    {
        $this->checklogin();
        $this->display();
    }

    public function password()
    {
        if (!$this->checklogin()) return;
        if (!$this->checkMustRepwd()) return;
        if ($_SESSION['UserInfo']['modify_pwd'] == '0') {
            header("Location: http://127.0.0.1/index.php?s=/Index/index");
            exit;
        }
        //账号密码方式需要客户端校验密码
        //Windows账号方式，由操作系统的密码策略进行校验
        $mUser = M('cuser');
        $cuser = $mUser->field('pwdpolicyid')->where("user_id='{$_SESSION['UserInfo']['user_id']}'")->find();
        if ($cuser['pwdpolicyid']) {
            $mPolicy = M("pwdpolicy");
            $_SESSION['PWDPolicy'] = $PWDPolicy = $mPolicy->where("id='{$cuser['pwdpolicyid']}' and Enable=1")->find();
            //"SELECT RemainDays,PWDChangePeriods,IncludeLetter,CapitalLetter,SmallLetter,IncludeNumber,Length,PWDRepeatDegree,IncludeSpecialCharacter from PWDPolicy where ID='{$PWDPolicyID}' and Enable=1"
            if ($_SESSION['PWDPolicy']) {
                $this->assign('PWDPolicy', $_SESSION['PWDPolicy']);
                $this->assign('length', $PWDPolicy['length']);
                $this->assign('smallletter', $PWDPolicy['smallletter']);
                $this->assign('capitalletter', $PWDPolicy['capitalletter']);
                $this->assign('includenumber', $PWDPolicy['includenumber']);
                $this->assign('includespecialcharacter', $PWDPolicy['includespecialcharacter']);
                $this->assign('pwdchangeperiods', $PWDPolicy['pwdchangeperiods']);
//                $this->assign('PWDRepeatDegree', $PWDPolicy['PWDRepeatDegree']);
            }
        }
        $this->display("password");
    }
}
