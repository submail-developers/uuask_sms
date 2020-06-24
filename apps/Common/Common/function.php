<?php
/**
 * [P 模板跳转 参数处理]
 * param array $data [数组]
 */
function P($data = array())
{
    $get = $_GET;
    unset($get['_URL_']);
    unset($get['p']);
    return U(strtolower(CONTROLLER_NAME) . '/' . ACTION_NAME, array_merge($get, $data));
}

function RU($url = '', $vars = '', $suffix = true, $domain = false)
{

    if (C('URL_ROUTER_ON') && C('URL_MODEL') == '2') {
        $rules = uu_get_cache('url');
        if (!empty($vars) && is_array($vars)) {
            $keys = array_keys($vars);
            $keys_str = '(' . implode('|', $keys) . ')';
        }
        $url_alias = strtolower($url . $keys_str);
        if (!empty($rules[$url_alias])) {
            $url = $rules[$url_alias]['locator'];
            foreach ($vars as $key => $value) {
                $url = str_replace('{' . $key . '}', $value, $url);
            }
            return U($url);
        }
    }
    return U($url, $vars, $suffix, $domain);
}

/**
 * 前台分页统一
 */
function pager($count, $pagesize = '')
{
    $pagesize = intval($pagesize);
    if (empty($pagesize)) {
        $pagesize = intval(C('UU_PAGE_SIZE'));
    }
    $pager = new Common\Lib\Page($count, $pagesize);
    $pager->rollPage = 8;
    $pager->setConfig('first', '首页');
    $pager->setConfig('prev', '上一页');
    $pager->setConfig('next', '下一页');
    $pager->setConfig('last', '最后一页');
    if (C('PLATFORM') == 'mobile') {
        $pager->setConfig('theme', '%upPage% <span>%nowPage%/%totalPage%</span> %downPage%');
    } else {
        $pager->setConfig('theme', '%first% %upPage% %linkPage% %downPage% %end%');
    }
    return $pager;
}

//获取页面SEO信息
function SEO($alias = 'home', $type = 'title', $array = '')
{
    $seo = uu_get_cache('seo');
    $data = $seo[$alias][$type];
    $data = str_replace('{SITENAME}', C('UU_SITE_NAME'), $data);
    if (!empty($array)) {
        foreach ($array as $key => $value) {
            $value = cut_str(strip_tags($value), 60);
            $data = str_replace('{' . $key . '}', $value, $data);
        }
    }
    return $data;
}

//获取缓存
function uu_get_cache($cachetype = '')
{

    $cache = F($cachetype);
    if ($cache) {
        return $cache;
    } else {
        return uu_refresh_cache($cachetype);
    }
}

//更新缓存
function uu_refresh_cache($cachetype = 'config')
{
    if ($cachetype == 'config') {//刷新配置文件
        $config = D('Config')->get_config();
        $path = CONF_PATH . 'uu.php';
        return uu_write_cache($path, $config);
    }
    if ($cachetype == 'admin_menu') {//刷新后台菜单
        $menu = M("AdminMenu");
        $data = $menu->order('m_sort desc,m_id asc')->where(' m_display = 1 ')->select();
        F('admin_menu', $data);
        return $data;
    }
    if ($cachetype == 'nav') {//刷新导航
        $menu = M("Nav");
        $data = $menu->where(' display = 1 ')->order('sort desc,id asc')->select();
        foreach ($data as $v) {
            $dli['type'] = $v['type'];
            $dli['title'] = $v['title'];
            $dli['pagealias'] = $v['pagealias'];
            $dli['target'] = $v['target'] ? "_blank" : "_self";
            if ($v['type'] == '1') {
                $dli['url'] = $v['url'] ? $v['url'] : C('UU_SITE_DOMAIN') . __ROOT__ . '/';
            } else {
                if ($v['act'] && $v['fun']) {
                    $dli['url'] = $v['act'] . "/" . $v['fun'];
                    $dli['url'] = U($dli['url']);
                } else {
                    $dli['url'] = C('UU_SITE_DOMAIN') . __ROOT__ . '/';
                }
            }
            $navli[] = $dli;
        }
        F('nav', $navli);
        return $navli;
    }
    if ($cachetype == 'cat') {//分类缓存
        $menu = M("Category");
        $data = $menu->order('c_sort desc,c_id asc')->select();
        foreach ($data as $v) {
            $v['url'] = $v['type'];
            if ($v['c_pid'] > 0) {
                $v['qurl'] = RU('question/lists', array('cat' => $v['c_pid'], 'cat1' => $v['c_id']));
                $v['aurl'] = RU('article/lists', array('cat' => $v['c_pid'], 'cat1' => $v['c_id']));
            } else {
                $v['qurl'] = RU('question/lists', array('cat' => $v['c_id']));
                $v['aurl'] = RU('article/lists', array('cat' => $v['c_id']));
            }
            $cat[$v['c_id']] = $v;
        }
        F('cat', $cat);
        return $cat;
    }
    if ($cachetype == 'seo') {//seo缓存
        $seo = M("Seo")->select();
        foreach ($seo as $v) {
            $list[$v['alias']] = $v;
        }
        F('seo', $list);
        return $list;
    }
    if ($cachetype == 'url') {//路由缓存
        $url = M("Url")->where(' is_display = 1 ')->select();
        foreach ($url as $v) {
            $list[$v['alias']] = $v;
            $configURL[$v['rule_key']] = $v['rule_var'];
        }
        F('url', $list);
        //写入路由配置文件
        $path = APP_PATH . 'home/Conf/url.php';
        $config_arr['URL_ROUTER_ON'] = C('UU_URL_RULE_OPEN');
        $config_arr['URL_ROUTE_RULES'] = $configURL;
        uu_write_cache($path, $config_arr);
        return $list;
    }
    if ($cachetype == 'oauth') {//oauth缓存
        $oauth = M("Oauth")->where(array('is_display' => 1))->order('sort desc')->getField('alias,name,config');
        F('oauth', $oauth);
        return $oauth;
    }
}

//写入缓存文件
function uu_write_cache($path, $array)
{
    $content = "<?php \nreturn ";
    $content .= var_export($array, true) . ";\r\n";
    $content .= "?>";
    if (file_put_contents($path, $content, LOCK_EX)) {
        return true;
    }
    return false;
}

//获取IP地址（中文地址）
function get_client_ip_txt($ip)
{
    if (empty($ip)) {
        $ip = get_client_ip();
    }
    $Iptxt = new \Common\ORG\IpLocation('UTFWry.dat');
    $val = $Iptxt->getlocation($ip);
    return $val['country'];
}

/**
 * 时间格式变换,友好提示
 */
function daterange($staday, $endday = '', $color = '#FF3300', $format = 'Y-m-d', $range = 7)
{
    if (empty($endday)) $endday = time();
    $value = $endday - $staday;
    if ($value < 0) {
        return '';
    } elseif ($value >= 0 && $value < 59) {
        $return = ($value + 1) . "秒前";
    } elseif ($value >= 60 && $value < 3600) {
        $min = intval($value / 60);
        $return = $min . "分钟前";
    } elseif ($value >= 3600 && $value < 86400) {
        $h = intval($value / 3600);
        $return = $h . "小时前";
    } elseif ($value >= 86400) {
        $d = intval($value / 86400);
        if ($d > $range) {
            return date($format, $staday);
        } else {
            $return = $d . "天前";
        }
    }
    if ($color) {
        $return = "<span style=\"color:{$color}\">" . $return . "</span>";
    }
    return $return;
}

/**
 * 获取上周,本周，上月，本月，本季度。的时间戳
 * @type=-1 昨天
 * @type=0 今天
 * @type=1 上周
 * @type=2 本周
 * @type=3 上月
 * @type=4 本月
 * @type=5 上季度
 * @type=6 本季度
 */
function get_mktime($type = '1')
{
    switch ($type) {
        case '-1':
            $firstday = mktime(0, 0, 0, date('m'), date('d') - 1, date('Y'));
            $lastday = mktime(0, 0, 0, date('m'), date('d'), date('Y')) - 1;
            break;
        case '0':
            $firstday = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
            $lastday = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;
            break;
        case '1':
            $firstday = mktime(0, 0, 0, date("m"), date("d") - date("w") + 1 - 7, date("Y"));
            $lastday = mktime(23, 59, 59, date("m"), date("d") - date("w") + 7 - 7, date("Y"));
            break;
        case '2':
            $firstday = mktime(0, 0, 0, date("m"), date("d") - date("w") + 1, date("Y"));
            $lastday = mktime(23, 59, 59, date("m"), date("d") - date("w") + 7, date("Y"));
            break;
        case '3':
            $firstday = mktime(0, 0, 0, date("m") - 1, 1, date("Y"));
            $lastday = mktime(23, 59, 59, date("m"), 0, date("Y"));
            break;
        case '4':
            $firstday = mktime(0, 0, 0, date("m"), 1, date("Y"));
            $lastday = mktime(23, 59, 59, date("m"), date("t"), date("Y"));
            break;
        case '5':
            $season = ceil((date('n')) / 3) - 1;//上季度是第几季度
            $firstday = mktime(0, 0, 0, $season * 3 - 3 + 1, 1, date('Y'));
            $lastday = mktime(23, 59, 59, $season * 3, date('t', mktime(0, 0, 0, $season * 3, 1, date("Y"))), date('Y'));
            break;
        case '6':
            $season = ceil((date('n')) / 3);//当月是第几季度
            $firstday = mktime(0, 0, 0, $season * 3 - 3 + 1, 1, date('Y'));
            $lastday = mktime(23, 59, 59, $season * 3, date('t', mktime(0, 0, 0, $season * 3, 1, date("Y"))), date('Y'));
            break;

    }
    return array($firstday, $lastday);
}

/**
 * 发送短信
 * param  varchar $mobile [手机号，多个用英文逗号隔开，最多200个]
 * param  array   $params [短信内容参数数组]
 * param  varchar $alias  [短信类型，用户获取短信模板编号]
 * return [type]         [description]
 *  用法：
 *  $mobile = '13855555555';
 *  $rand=mt_rand(100000, 999999);
 *  $params = array('code'=>$rand.'','product'=>C('SMS_SIGNATURE'));
 *  send_sms($mobile,$params,'reg');
 */
function send_sms($mobile, $params, $alias)
{
    $gateway = "https://api.mysubmail.com/";
    $sms_open = C('UU_SMS_OPEN');
    if ($sms_open <> '1') {
        $return['status'] = 0;
        $return['content'] = '发送失败，短信功能未开启！';
        return $return;
    }
//    $config = array(
//        'appid' => C('UU_SMS_APPKEY'),
//        'signature' => C('UU_SMS_SECRETKEY'),
//        'taitou' => C('UU_SMS_SIGNATURE')
//    );
    $data['appid'] = C('UU_SMS_APPKEY'); //短信平台帐号
    $data['signature'] = C('UU_SMS_SECRETKEY'); //短信平台密码
    $data['to'] =   $mobile;
    $templateCode = getTemplateCode($alias);
    /**
     * xsend,走模板
     */
    if($templateCode){
        $gateway .= "message/xsend.json";
        $data['project']    =   $templateCode;
        $data['vars']   =   json_encode($params);
        $result =    submailPost($gateway,$data);
        if($result['status']!=='success'){
            $return['status'] = 0;
            $return['content'] = '发送失败，短信模板配置出错！';
            return $return;
        }
        $return['status']=1;
        $return['content']='发送成功';
        return $return;
    }

    $templateContent = getTemplateContent($alias);
    if($templateContent){
        $gateway .= "message/send.json";
        $data['content']    =  '【'. C('UU_SMS_SIGNATURE').'】'.$templateContent;
        $result =    submailPost($gateway,$data);
        if($result['status']!=='success'){
            $return['status'] = 0;
            $return['content'] = '发送失败，短信模板配置出错！';
            return $return;
        }
        $return['status']=1;
        $return['content']='发送成功';
        return $return;
    }

    $return['status'] = 0;
    $return['content'] = '发送失败，短信模板配置出错！';
    return $return;
}


/**
 * 获取短信模板内容
 * @return varchar $alias [短信类型，用户获取短信模板编号]
 * @author submail sms
 */
function getTemplateContent($alias)
{
   return trim(M('SmsTpl')->where(array('alias' => $alias))->find()['tpl']);
}

function submailPost($smsapi,$data){
    $query = http_build_query($data);
    $options['http'] = array(
        'timeout' => 60,
        'method' => 'POST',
        'header' => 'Content-type:application/x-www-form-urlencoded',
        'content' => $query
    );
    $context = stream_context_create($options);
    $result = file_get_contents($smsapi, false, $context);
    $output = trim($result, "\xEF\xBB\xBF");
    return json_decode($output, true);
}

/**
 * 获取短信模板编号
 * @return varchar $alias [短信类型，用户获取短信模板编号]
 */
function getTemplateCode($alias)
{
    $tpl_info = M('SmsTpl')->where(array('alias' => $alias))->find();
    return trim($tpl_info['tplid']);
}

/**
 * 生成字符
 */
function randstr($length = 6)
{
    $hash = '';
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz@#!~?:-=';
    $max = strlen($chars) - 1;
    mt_srand((double)microtime() * 1000000);
    for ($i = 0; $i < $length; $i++) {
        $hash .= $chars[mt_rand(0, $max)];
    }
    return $hash;
}

/**
 * 密码加密
 * param string $path 密码(明码)
 * param string $value 加密字符
 * return string
 */
function get_pwdmd5($pwd = '', $pwd_hash = '')
{
    $type = C('UU_PWD_MD5TYPE');
    if ($type == "1" || empty($type)) {//默认加密方式
        return md5(md5($pwd) . 'uuask' . md5($pwd_hash));
    }
    if ($type == "2") {
        return md5($pwd);
    }
    if ($type == "3") {
        return md5($pwd . $pwd_hash);
    }
}

//清除标签，转换换行符
function uu_strip_tags($str)
{
    $str = strip_tags($str);
    $str = str_replace(chr(32), '&nbsp;', $str);
    return nl2br($str);
}

//截取字符串
function cut_str($sourcestr, $cutlength, $start = 0, $dot = '...')
{
    $returnstr = '';
    $i = 0;
    $n = 0;
    $str_length = strlen($sourcestr);
    $mb_str_length = mb_strlen($sourcestr, 'utf-8');
    while (($n < $cutlength) && ($i <= $str_length)) {
        $temp_str = substr($sourcestr, $i, 1);
        $ascnum = ord($temp_str);
        if ($ascnum >= 224) {
            $returnstr = $returnstr . substr($sourcestr, $i, 3);
            $i = $i + 3;
            $n++;
        } elseif ($ascnum >= 192) {
            $returnstr = $returnstr . substr($sourcestr, $i, 2);
            $i = $i + 2;
            $n++;
        } elseif (($ascnum >= 65) && ($ascnum <= 90)) {
            $returnstr = $returnstr . substr($sourcestr, $i, 1);
            $i = $i + 1;
            $n++;
        } else {
            $returnstr = $returnstr . substr($sourcestr, $i, 1);
            $i = $i + 1;
            $n = $n + 0.5;
        }
    }
    if ($mb_str_length > $cutlength) {
        $returnstr = $returnstr . $dot;
    }
    return $returnstr;
}

//获取当前页面URL
function uu_get_url()
{
    $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
    $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
    $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : $path_info);
    return $sys_protocal . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $relate_url;
}

?>
