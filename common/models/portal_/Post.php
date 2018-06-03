<?php

namespace common\models\portal_;


use common\OctaneComponents\exceptions\ModelNotFoundException;
use common\helpers\announcement\AnnouncementBuilder;
use common\OctaneComponents\constants\Job;
use common\models\Announcement2;
use common\models\Country;
use common\models\EducationTitle;
use common\models\Experience;
use common\models\Profession;
use common\models\Settings;
use common\models\State;
use Yii;
use yii\db\ActiveQuery;
use common\OctaneComponents\OActiveRecord;
use common\models\ProfessionPortalCategory;
use yii\base\Exception;
use yii\helpers\Html;
use yii\helpers\Inflector;
use common\models\User;
use backend\components\PostingAppHelper;
use backend\models\Announcement;
use backend\models\posting_\Credential;
use backend\models\posting_\Portal;
use backend\models\posting_\PortalPost;
use common\OctaneComponents\interfaces\PersonInterface;
use yii\httpclient\Client as HttpClient;

/**
 * This is the model class for table "{{%portal_post}}".
 *
 * @property integer $id
 * @property integer $announcement_id
 * @property integer $portal_id
 * @property integer $status
 * @property string $link
 * @property string $date_start
 * @property integer $date_end
 * @property integer $apply_count
 * @property integer $created_at
 * @property integer $updated_at
 * @property integer $created_by
 * @property integer $updated_by
 * @property integer $deleted_by
 * @property integer $deleted_at
 *
 * @property Portal $portal
 */
class Post extends OActiveRecord
{
    const STATUS_POSTED    = 1;
    const STATUS_SENT      = 2;
    const STATUS_NOTPOSTED = 3;
    const STATUS_OUTDATED  = 4;

    /* TURN ON Original mode */
    private $_postInDefault = false;
    private $_checkBalance  = true;

    /* TURN ON Debugger mode */
//    private $_postInDefault = true;
//    private $_checkBalance  = false;

    private $curlHandler = null;

    private $_loginOlx         = '';
    private $_passOlx          = '';
    private $_loginGumtree     = '';
    private $_passGumtree      = '';
    private $_loginLento       = '';
    private $_passLento        = '';
    private $_loginGazetapraca = '';
    private $_passGazetapraca  = '';
    private $_loginGoldenline  = '';
    private $_passGoldenline   = '';
    private $_loginInfopraca   = '';
    private $_passInfopraca    = '';
    private $_loginPracujpl    = '';
    private $_passPracujpl     = '';

    private $_letOlx         = false;
    private $_letGumtree     = false;
    private $_letLento       = false;
    private $_letGazetapraca = false;
    private $_letGoldenline  = false;
    private $_letInfopraca   = false;
    private $_letPracujpl    = false;

    private $_logF = '';

    private $_loginUrlOlx         = '';
    private $_loginUrlGumtree     = '';
    private $_loginUrlLento       = '';
    private $_loginUrlGazetapraca = '';
    private $_loginUrlGoldenline  = '';
    private $_loginUrlInfopraca   = '';
    private $_loginUrlPracujpl    = '';

    private $_postUrlOlx         = '';
    private $_postUrlGumtree     = '';
    private $_postUrlLento       = '';
    private $_postUrlGazetapraca = '';
    private $_postUrlGoldenline  = '';
    private $_postUrlInfopraca   = '';
    private $_postUrlPracujpl    = '';

    //private $_postingPriceOlx = 0;
    private $_postingPriceOlx         = null;
    private $_postingPriceGumtree     = null;
    private $_postingPriceLento       = null;
    private $_postingPriceGazetapraca = null;
    private $_postingPriceGoldenline  = null;
    private $_postingPriceInfopraca   = null;
    private $_postingPricePracujpl    = null;

    private $_updatedOffers = [
        'olx'         => [],
        'gumtree'     => [],
        'lento'       => [],
        'gazetapraca' => [],
        'infopraca'   => [],
        'pracujpl'    => [],
    ];

    function __construct()
    {
        if (!file_exists(Yii::getAlias('@runtime') . '/posting'))
            mkdir(Yii::getAlias('@runtime') . '/posting', 0777, true);

        if (!file_exists(Yii::getAlias('@runtime') . '/posting/logs'))
            mkdir(Yii::getAlias('@runtime') . '/posting/logs', 0777, true);

        if (!file_exists(Yii::getAlias('@runtime') . '/posting/cookies'))
            mkdir(Yii::getAlias('@runtime') . '/posting/cookies', 0777, true);

        parent::__construct();
    }

    /**
     * @return string Name of the Model
     */
    public static function modelName()
    {
        Yii::t('app', 'Publikacja');
    }

    /**
     * @return string Raw table name of the Model
     */
    public static function tableRawName()
    {
        return 'portal_post';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['announcement_id', 'portal_id'], 'required'],
            [['announcement_id', 'portal_id', 'status', 'apply_count', 'created_at', 'updated_at',
              'created_by', 'updated_by', 'deleted_by', 'deleted_at'], 'integer'],
            [['link', 'date_start', 'date_end'], 'string', 'max' => 255],
            [['portal_id'], 'exist', 'skipOnError' => true, 'targetClass' => Portal::className(),
             'targetAttribute'                     => ['portal_id' => 'id']],
            //[['date_start' , 'date_end'], 'date'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'              => Yii::t('app', 'ID'),
            'announcement_id' => Yii::t('app', 'Announcement ID'),
            'portal_id'       => Yii::t('app', 'Portal ID'),
            'status'          => Yii::t('app', 'Status'),
            'link'            => Yii::t('app', 'Link'),
            'date_start'      => Yii::t('app', 'Date Start'),
            'date_end'        => Yii::t('app', 'Date End'),
            'apply_count'     => Yii::t('app', 'Apply Count'),
            'created_at'      => Yii::t('app', 'Created At'),
            'updated_at'      => Yii::t('app', 'Updated At'),
            'created_by'      => Yii::t('app', 'Created By'),
            'updated_by'      => Yii::t('app', 'Updated By'),
            'deleted_by'      => Yii::t('app', 'Deleted By'),
            'deleted_at'      => Yii::t('app', 'Deleted At'),
        ];
    }

    /**
     * @return ActiveQuery
     */
    public function getPortal()
    {
        return $this->hasOne(Portal::className(), ['id' => 'portal_id']);
    }

    /**
     * Go CURL!
     * @return null|resource
     */
    protected function getCurlHandler()
    {
        if (null == $this->curlHandler) $this->curlHandler = curl_init();

        return $this->curlHandler;
    }

    /**
     * Initialize credentials
     */
    private function initCredentials()
    {
        $user_id     = Yii::$app->user->id;
        $credentials = Credential::find()->joinWith('siteInfo')->where(['user_id' => $user_id])->all();

        foreach ($credentials as $credential) {
            switch ($credential["siteInfo"]["name"]) {
                case 'olx':
                    $this->_loginOlx        = $credential["login"];
                    $this->_passOlx         = $credential["password"];
                    $this->_letOlx          = $credential["turnon"];
                    $this->_loginUrlOlx     = $credential["siteInfo"]["login_link"];
                    $this->_postUrlOlx      = $credential["siteInfo"]["post_link"];
                    $this->_postingPriceOlx = $credential["siteInfo"]["min_credit"];
                    break;
                case 'gumtree':
                    $this->_loginGumtree        = $credential["login"];
                    $this->_passGumtree         = $credential["password"];
                    $this->_letGumtree          = $credential["turnon"];
                    $this->_loginUrlGumtree     = $credential["siteInfo"]["login_link"];
                    $this->_postUrlGumtree      = $credential["siteInfo"]["post_link"];
                    $this->_postingPriceGumtree = $credential["siteInfo"]["min_credit"];
                    break;
                case 'lento':
                    $this->_loginLento        = $credential["login"];
                    $this->_passLento         = $credential["password"];
                    $this->_letLento          = $credential["turnon"];
                    $this->_loginUrlLento     = $credential["siteInfo"]["login_link"];
                    $this->_postUrlLento      = $credential["siteInfo"]["post_link"];
                    $this->_postingPriceLento = $credential["siteInfo"]["min_credit"];
                    break;
                case 'gazetapraca':
                    $this->_loginGazetapraca        = $credential["login"];
                    $this->_passGazetapraca         = $credential["password"];
                    $this->_letGazetapraca          = $credential["turnon"];
                    $this->_loginUrlGazetapraca     = $credential["siteInfo"]["login_link"];
                    $this->_postUrlGazetapraca      = $credential["siteInfo"]["post_link"];
                    $this->_postingPriceGazetapraca = $credential["siteInfo"]["min_credit"];
                    break;
                case 'goldenline':
                    $this->_loginGoldenline        = $credential["login"];
                    $this->_passGoldenline         = $credential["password"];
                    $this->_letGoldenline          = $credential["turnon"];
                    $this->_loginUrlGoldenline     = $credential["siteInfo"]["login_link"];
                    $this->_postUrlGoldenline      = $credential["siteInfo"]["post_link"];
                    $this->_postingPriceGoldenline = $credential["siteInfo"]["min_credit"];
                    break;
                case 'infopraca':
                    $this->_loginInfopraca        = $credential["login"];
                    $this->_passInfopraca         = $credential["password"];
                    $this->_letInfopraca          = $credential["turnon"];
                    $this->_loginUrlInfopraca     = $credential["siteInfo"]["login_link"];
                    $this->_postUrlInfopraca      = $credential["siteInfo"]["post_link"];
                    $this->_postingPriceInfopraca = $credential["siteInfo"]["min_credit"];
                    break;
                case 'pracujpl':
                    $this->_loginPracujpl        = $credential["login"];
                    $this->_passPracujpl         = $credential["password"];
                    $this->_letPracujpl          = $credential["turnon"];
                    $this->_loginUrlPracujpl     = $credential["siteInfo"]["login_link"];
                    $this->_postUrlPracujpl      = $credential["siteInfo"]["post_link"];
                    $this->_postingPricePracujpl = $credential["siteInfo"]["min_credit"];
                    break;

            }
        }

    }

    /**
     * Runs updating
     */
    public static function runUpdating($opt = [])
    {
        $runUpdate = new Post();
        $runUpdate->updateOffersByParams(null, null, $opt);

    }

    /**
     * Runs updating
     * @param $sites
     * @param $ids
     * @param array $opt
     * @return mixed
     */
    public static function runUpdatingPicked($sites, $ids, $opt = [])
    {
        $runUpdate = new Post();

        //$runUpdate->updateOffersByParams($ids);
        return $runUpdate->updateOffersByParams($sites, $ids, $opt);
    }

    /**
     * Login and update offers to each site if publishing is enabled.
     * After all generate callback and save logs.
     * @param array $sites
     * @param array $ids
     * @param array $opt
     * @return mixed
     */
    public function updateOffersByParams($sites = [], $ids = [], $opt = [])
    {
        $this->_logF = Yii::getAlias('@runtime') . '/posting/logs/log_' . date('Y_m_d') . '.txt';

        $logged   = null;
        $posted   = null;
        $callback = null;
        $caniPost = false;

        $this->initCredentials();

        $this->saveLogIntro($this->_logF);

        if (sizeof($sites) <= 0)
            $sites = [
                1    => 'olx',
                2    => 'gumtree',
                3    => 'lento',
                4    => 'gazetapraca',
                5    => 'goldenline',
                6    => 'infopraca',
                7    => 'pracujpl',
                9999 => 'interimax',
                9998 => 'ateam',
            ]; //if all sites


        foreach ($sites as $site) {
            switch ($site) {
                case 'olx':
                    $caniPost = $this->_letOlx;
                    break;
                case 'gumtree':
                    $caniPost = $this->_letGumtree;
                    break;
                case 'lento':
                    $caniPost = $this->_letLento;
                    break;
                case 'gazetapraca':
                    $caniPost = $this->_letGazetapraca;
                    break;
                case 'goldenline':
                    $caniPost = $this->_letGoldenline;
                    break;
                case 'infopraca':
                    $caniPost = $this->_letInfopraca;
                    break;
                case 'pracujpl':
                    $caniPost = $this->_letPracujpl;
                    break;
                case 'interimax':
                    $caniPost = true; //always can
                    break;
                case 'ateam':
                    $caniPost = true; //always can
                    break;
            }
            //}

            $site_name = $site;

            //if ($caniPost && $this->isBalanceOk($site_name)) {
            //if ($caniPost) {
            if (
            ($this->_checkBalance && ($caniPost && $this->isBalanceOk($site_name)) //for original mode
                ||
                (!$this->_checkBalance && $caniPost)) //for debugger mode
            ) {
                $logged = $this->loginto($site_name);
                if ($logged["status"] == 'success') {
                    if (sizeof($ids) == 0) {
                        //$this->getNonActiveOffersIds($site_name)
                        $posted = $this->updateOffers(null, $site_name, $this->_logF, $opt);
                    } else {
                        $posted = $this->updateOffers($ids, $site_name, $this->_logF, $opt);
                    }
                } else {
                    $posted = ['status' => $logged["status"], "message" => $logged["message"]];
                }

                $callback .= $this->generateCallback($site_name, $logged, $posted);
            } else {
                $callback .= $this->generateCallbackOff($site_name, $caniPost);
            }
        }

        $this->saveLogOutro($this->_logF);

        return $callback;
        //echo $callback;

    }

    /**
     * Set login to Olx
     * @param $login
     * @param $pass
     * @param $loginUrl
     * @return array
     */
    public function setLoginOlx($login, $pass, $loginUrl)
    {
        $headers = [
            'Host:ssl.olx.pl',
            'Origin:https://ssl.olx.pl',
            'Referer:' . $loginUrl,
            'Upgrade-Insecure-Requests:1',
        ];

        $result = $this->sendPost($loginUrl, [
            'ref[0][action]'  => 'myaccount',
            'ref[0][method]'  => 'index',
            'login[email]'    => $login,
            'login[password]' => $pass

            /*$loginInputName => $login,
            $passInputName => $pass,
            $buttonName => $buttonLabel,*/
        ], $headers);

        /* If moved permamently after login which OLX does */
        if (empty($result) && $this->getCurlCode() == 301) {
            return ["status" => "success"];
        } else {
            return ["status" => "error", "message" => Yii::t('app', 'Logowanie nie powiodło się!')];
        };
    }

    /**
     * Set login to Gumtree
     * @param $login
     * @param $pass
     * @param $loginUrl
     * @return array
     */
    public function setLoginGumtree($login, $pass, $loginUrl)
    {

        $loginUrl = 'https://www.gumtree.pl/login';
        $headers  = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: pl,en-US;q=0.7,en;q=0.3',
            'Connection: keep-alive',


            'Host: www.gumtree.pl',
            'Origin: http://www.gumtree.pl',
            'Referer: https://www.gumtree.pl/login.html?redirect=http://www.gumtree.pl/my/ads.html',
            'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
            'Upgrade-Insecure-Requests: 1',
            'Expect: ',
            'application/xhtml+voice+xml;version=1.2, application/x-xhtml+voice+xml;version=1.2, text/html, application/xml;q=0.9, application/xhtml+xml, image/png, image/jpeg, image/gif, image/x-xbitmap, */*;q=0.1',
            'Content-type: application/x-www-form-urlencoded;charset=UTF-8',
        ];


        $headers2 = [
            'Host:www.gumtree.pl',
            'Origin:http://www.gumtree.pl',
            'Referer:https://www.gumtree.pl/login.html?redirect=http://www.gumtree.pl/my/ads.html',
            'Upgrade-Insecure-Requests:1',
            'Expect: ',
            'application/xhtml+voice+xml;version=1.2, application/x-xhtml+voice+xml;version=1.2, text/html, application/xml;q=0.9, application/xhtml+xml, image/png, image/jpeg, image/gif, image/x-xbitmap, */*;q=0.1',
            'Connection: Keep-Alive',
            'Content-type: application/x-www-form-urlencoded;charset=UTF-8',
        ];


        $params = 'redirect=http%3A%2F%2Fwww.gumtree.pl%2Fmy%2Fads.html&email=<email>&password=<pass>';
        $data2  = [
            'email'    => '<username>',
            'password' => '<pass>',
            'redirect' => '',
        ];

        $result = $this->sendPost($loginUrl, $params, $headers);

        $code = $this->getCurlCode();

        //Redirect to my ads

        return ($result == '' && $code == 302)
            ? ["status" => "success"]
            : ["status" => "error"];
    }

    /**
     * Log into Lento
     * @param $login
     * @param $pass
     * @param $loginUrl
     * @return array
     */
    public function setLoginLento($login, $pass, $loginUrl)
    {
        $headers = [
            'X-Requested-With: XMLHttpRequest',
            'application/x-www-form-urlencoded',
            'Expect:  ',
            'Host: www.lento.pl',
            'Origin: http://www.lento.pl',
            'Referer:' . $loginUrl,
            'Accept-Language: pl,en-US;q=0.7,en;q=0.3',
            'Connection: keep-alive',
        ];

        $result1 = $this->sendGet($loginUrl);

        $result = $this->sendPost($loginUrl, [
            'auto_login' => 1,
            'tologin'    => 1,
            'user_mail'  => '' . trim($login),
            'user_pass'  => '' . trim($pass),
        ], $headers);

        /* If logged in */
        if ($this->getCurlCode() == 200) {
            return ["status" => "success"];
        } else {
            return empty($result) ? ["status" => "success"] : ["status"  => "error",
                                                               "message" => "Login process failed. "];
        }
    }

    /**
     * Set login to Gazetapraca
     * @param $login
     * @param $pass
     * @param $loginUrl
     * @return array
     */
    public function setLoginGazetapraca($login, $pass, $loginUrl)
    {
        $headers = [
            'X-Requested-With: XMLHttpRequest',
            'application/x-www-form-urlencoded',
            'Expect:  ',
            'Host:gazetapraca.pl',
            'Origin:http://gazetapraca.pl',
            'Referer:' . $loginUrl,
            'Upgrade-Insecure-Requests:1',
        ];

        $result = $this->sendPost($loginUrl, [
            'errorPage' => '/2,126.html',
            'nextPage'  => '/0,0.html',
            'name'      => $login,
            'password'  => $pass,
            'submit'    => 'Zaloguj się',
        ], $headers);

        /* If logged in */
        if ($this->getCurlCode() == 200) {
            return ["status" => "success"];
        } else {
            return empty($result) ? ["status" => "success"] : ["status"  => "error",
                                                               "message" => "Login process failed. "];
        }
    }

    /**
     * Set loging to Goldenline
     *
     * https://www.goldenline.pl/logowanie/ecommerce/c61881b8e620d6e333e12ba5f524cc7f/15482e4fc4ce917c88843414a9d1c809?next=https://panel.goldenline.pl/ecommerce/offers/list/:
     * https://www.goldenline.pl/logowanie/ecommerce/
     *
     * @param $login
     * @param $pass
     * @param $loginUrl
     * @return array
     */
    public function setLoginGoldenline($login, $pass, $loginUrl)
    {
        $loginUrl = 'https://www.goldenline.pl/logowanie/ecommerce/c61881b8e620d6e333e12ba5f524cc7f/15482e4fc4ce917c88843414a9d1c809?next=https://panel.goldenline.pl/ecommerce/offers/list/';
        $headers = [
            'Host: www.goldenline.pl',
            'Referer: https://www.goldenline.pl/logowanie/ecommerce/',
            'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
        ];

        //get cookie token
        $result  = $this->sendGet($loginUrl);
        $pattern = '~name="_csrf_token" value="(.*?)"~';

        preg_match_all(
            $pattern,
            (string)$result,
            $feedback
        );

        $cookieToken = $feedback[1][0];

        $result = $this->sendPost($loginUrl, http_build_query([
            'login'        => $login,
            'passwd'       => $pass,
            'remember'     => 'off',
            '_target_path' => '',
            '_csrf_token'  => $cookieToken,
        ]), $headers);


        //$result = $this->sendGet($loginUrl);
        if ($this->getCurlCode() == 302) {
            //https://panel.goldenline.pl/ecommerce/offers/list/?__gtoken=e9fb4e08721e56bb7351c783b208b53e
            $redirect_to = $this->getCurlInfo()["redirect_url"];
        }

        /* If logged in */
        if ($this->getCurlCode() == 200 || $this->getCurlCode() == 302) {
            return ["status" => "success", "token" => $cookieToken, "redirect_to" => $redirect_to];
        } else {
            return empty($result) ? ["status" => "success"] : ["status"  => "error",
                                                               "message" => "Login process failed. "];
        }
    }

    /**
     * Set login to Infopraca
     * @param $login
     * @param $pass
     * @param $loginUrl
     * @return array
     */
    public function setLoginInfopraca($login, $pass, $loginUrl)
    {
        $headers = [
            'Expect:  ',
            'Host:www.infopraca.pl',
            'Origin:https://www.infopraca.pl',
            'Referer:' . $loginUrl,
            'Upgrade-Insecure-Requests:1',
        ];

        $result = $this->sendPost($loginUrl, [
            'Identifier' => $login,
            'Password'   => $pass,
        ], $headers);

        /* If logged in */
        if ($this->getCurlCode() == 200) {
            return ["status" => "success"];
        } else {
            return empty($result) ? ["status" => "success"] : ["status"  => "error",
                                                               "message" => "Login process failed. "];
        }
    }

    /**
     * Set login to pracuj.pl
     * @param $login
     * @param $pass
     * @param $loginUrl
     * @return array
     * https://sklep.pracuj.pl/Account/Login.aspx?_ga=1.74753298.148698083.1474233384
     */
    public function setLoginPracujpl($login, $pass, $loginUrl)
    {

        //Set variables
        $result = $this->sendPost($loginUrl, null);

        $t         = explode('name="__VIEWSTATE" id="__VIEWSTATE" value="', $result);
        $t         = explode('" />', $t[1]);
        $viewstate = $t[0];

        $t        = explode('name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="', $result);
        $t        = explode('" />', $t[1]);
        $eventval = $t[0];

        //Login
        $headers = [
            'Host:sklep.pracuj.pl',
            'Origin:https://sklep.pracuj.pl',
            'Referer:https://sklep.pracuj.pl/Account/Login.aspx',
            'Upgrade-Insecure-Requests:1',
        ];

        $result = $this->sendPost($loginUrl, [
            '__EVENTARGUMENT'                  => '',
            '__EVENTTARGET'                    => '',
            '__EVENTVALIDATION'                => $eventval,
            '__VIEWSTATE'                      => $viewstate,
            'ctl00$DefaultContent$btnLogin'    => 'Zaloguj się',
            'ctl00$DefaultContent$tbxLogin'    => $login,
            'ctl00$DefaultContent$tbxPassword' => $pass,
            'ctl00$hfEmail'                    => '',
        ], $headers);

        $pattern1 = '/Offers/Dashboard.aspx';
        $pattern2 = '~<h2>Object moved to <a href="(.*?)">here</a>.</h2>~';

        preg_match_all(
            $pattern2,
            (string)$result,
            $feedback
        );
        $redirUrl = $feedback[1][0];

        //Login Redir
        $headers = [
            'Host:sklep.pracuj.pl',
            'Referer:https://sklep.pracuj.pl/Account/Login.aspx',
            'User-Agent:Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
        ];

        $result2 = $this->sendPost('https://sklep.pracuj.pl/' . $redirUrl, [], $headers);
        //check if logged

        /* If logged in */
        if (($this->getCurlCode() == 200 || $this->getCurlCode() == 302) && $feedback[1][0] === $pattern1) {
            return ["status" => "success"];
        } else {
            return empty($result) ? ["status" => "success"] : ["status"  => "error",
                                                               "message" => "Login process failed. "];
        }
    }

    /**
     * Log into given website
     * @param $name
     * @return array given by loginto, if logintoX returned no error
     */
    protected function loginto($name)
    {
        $captcha    = null;
        $iscaptcha  = null;
        $errorsHtml = null;

        switch ($name) {
            case 'olx':
                $returned = $this->logintoOlx();
                if (isset($returned["status"]) && $returned["status"] == "error") return $returned;
                break;
            case 'gumtree':
                $returned = $this->logintoGumtree();
                if (isset($returned["status"]) && $returned["status"] == "error") return $returned;
                break;
            case 'lento':
                $returned = $this->logintoLento();
                if (isset($returned["status"]) && $returned["status"] == "error") return $returned;
                break;
            case 'gazetapraca':
                //$returned    = $this->logintoGazetapraca(); //gazeta praca ma oferty z goldenline
                $returned = $this->logintoGoldenline();
                if (isset($returned["status"]) && $returned["status"] == "error") return $returned;
                break;
            case 'infopraca':
                $returned = $this->logintoInfopraca();
                if (isset($returned["status"]) && $returned["status"] == "error") return $returned;
                break;
            case 'pracujpl':
                $returned = $this->logintoPracujpl();
                if (isset($returned["status"]) && $returned["status"] == "error") return $returned;
                break;
            case 'interimax':
                $returned["status"] = "success";

                return $returned;
                break;
            case 'ateam':
                $returned["status"] = "success";

                return $returned;
                break;
        }

        return ["status" => "success"];

    }

    /**
     * Log into OLX
     */
    public function logintoOlx()
    {
        $return   = $this->setLoginOlx($this->_loginOlx, $this->_passOlx, $this->_loginUrlOlx);
        $callback = $this->getCurlInfo();

        return $return;
    }

    /**
     * Log into Gumtree
     */
    public function logintoGumtree()
    {
        $return   = $this->setLoginGumtree($this->_loginGumtree, $this->_passGumtree, $this->_loginUrlGumtree);
        $callback = $this->getCurlInfo();

        $staticRedirectUrl = "http://www.gumtree.pl/login.html?error&message=Bad+credentials";
        if ($this->getCurlCode() != 302 && $callback["redirect_url"] = $staticRedirectUrl) {
            return ["status" => "error", "message" => "Wrong credentials or login link"];
        } else {
            return array_merge($return, ['redirect_url' => $callback["redirect_url"]]);
        }

    }

    /**
     * Log into Lento
     */
    public function logintoLento()
    {
        $return   = $this->setLoginLento($this->_loginLento, $this->_passLento, $this->_loginUrlLento);
        $callback = $this->getCurlInfo();

        return $return;
    }

    /**
     * Log into Gazeta Praca
     */
    public function logintoGazetapraca()
    {
        $return   = $this->setLoginGazetapraca($this->_loginGazetapraca, $this->_passGazetapraca, $this->_loginUrlGazetapraca);
        $callback = $this->getCurlInfo();

        return $return;
    }

    /**
     * Log into Goldenline
     * https://www.goldenline.pl/logowanie/ecommerce/c61881b8e620d6e333e12ba5f524cc7f/15482e4fc4ce917c88843414a9d1c809?next=https://panel.goldenline.pl/ecommerce/offers/list/:
     * https://www.goldenline.pl/logowanie/ecommerce/
     */
    public function logintoGoldenline()
    {
        $return   = $this->setLoginGoldenline($this->_loginGoldenline, $this->_passGoldenline, $this->_loginUrlGoldenline);
        $callback = $this->getCurlInfo();

        return $return;
    }

    /**
     * Log into Infopraca
     */
    public function logintoInfopraca()
    {
        $return   = $this->setLoginInfopraca($this->_loginInfopraca, $this->_passInfopraca, $this->_loginUrlInfopraca);
        $callback = $this->getCurlInfo();

        return $return;
    }

    /**
     * Log into Pracuj.pl
     */
    public function logintoPracujpl()
    {
        $return   = $this->setLoginPracujpl($this->_loginPracujpl, $this->_passPracujpl, $this->_loginUrlPracujpl);
        $callback = $this->getCurlInfo();

        return $return;
    }

    /**
     * Picks website and posts offer if it wasn't posted yet
     * @param $offerData
     * @param $site
     * @param $logFile
     * @param array $opt
     * @return array
     */
    public function postOffer($offerData, $site, $logFile, $opt = [])
    {
        $createdBy = User::find()->where(['id' => $offerData["created_by"]])->one();

        if (!empty($this->curlHandler)) curl_close($this->curlHandler);
        $this->curlHandler = curl_init();

        switch ($site) {
            case 'olx':
                $returned = $this->postOfferOlx($this->_postUrlOlx, $offerData, $createdBy, $opt);
                break;
            case 'gumtree':
                $returned = $this->postOfferGumtree($this->_postUrlGumtree, $offerData, $createdBy, $opt);
                break;
            case 'lento':
                $returned = $this->postOfferLento($this->_postUrlLento, $offerData, $createdBy, $opt);
                break;
            case 'gazetapraca': //z goldenline
                $returned = $this->postOfferGazetapraca($this->_postUrlGazetapraca, $offerData, $createdBy, $opt);
                break;
            case 'goldenline':
                $returned = $this->postOfferGoldenline($this->_postUrlGoldenline, $offerData, $createdBy, $opt);
                break;
            case 'infopraca':
                $returned = $this->postOfferInfopraca($this->_postUrlInfopraca, $offerData, $createdBy, $opt);
                break;
            case 'pracujpl':
                $returned = $this->postOfferPracujpl($this->_postUrlPracujpl, $offerData, $createdBy, $opt);
                break;
            case 'interimax':
                $returned = $this->postOfferInterimax($offerData, $opt);
                break;
            case 'ateam':
                $returned = $this->postOfferAteam($offerData, $opt);
                break;
        }

        /* Depends on status - save log */
        if (isset($returned["status"])) {
        }

        return [
            'status'  => $returned["status"],
            'message' => $this->renderPostingCallback($returned, $offerData, $site),
            'code'    => isset($returned["code"]) ? $returned["code"] : null,
            'link'    => isset($returned["link"]) ? $returned["link"] : null,
        ];
    }

    /**
     * Posts offer OLX
     * @param $postOfferUrl
     * @return array
     */
    public function postOfferOlx($postOfferUrl, $offerInfo, $createdBy, $opt = [])
    {
        $login        = $this->logintoOlx();
        $recruitment  = $offerInfo->recruitment;
        $ppcategory   = new ProfessionPortalCategory();
        $ppref        = $ppcategory->FindByProfessionAndPortal($recruitment->profession_id, 1);
        $category_ref = $ppref->getPortalCategory()->one()->ref_id;
        $adding_key   = null;

        $fullname = ($createdBy["first_name"] != NULL && $createdBy["last_name"] != NULL)
            ? $createdBy["first_name"] . ' ' . $createdBy["last_name"]
            : $createdBy["email"];

        $client = $recruitment->client !== null ? $recruitment->client->name : 'nie podano';
        $email  = $createdBy["email"];
        $phone  = $createdBy["phone"];

        if (isset($offerInfo->contract_id)) {
            $contract = $offerInfo->contract_id;
        } else if (isset($offerInfo->recruitment->contract)) {
            $contract = $offerInfo->recruitment->contract;
        } else {
            $contract = '6';
        }

        if (isset($offerInfo->work_type_id)) {
            $jobType = $offerInfo->work_type_id;
        } else if (isset($offerInfo->recruitment->work_type_id)) {
            $jobType = $offerInfo->recruitment->work_type_id->value;
        } else {
            $jobType = '4';
        }

        switch ($contract) {
            case Job::CONTRACT_FULL:
                $contract = 'fulltime';
                break;
            case Job::CONTRACT_HALF:
                $contract = 'halftime';
                break;
            case Job::CONTRACT_THIRD:
                $contract = 'parttime';
                break;
            case Job::CONTRACT_OTHER:
                $contract = 'practice';
                break;
        }

        switch ($jobType) {
            //case Job::WORK_TYPE_OTHER: //dowolne
            case Job::WORK_TYPE_FULL_TIME:
                $jobType = 'part';
                break; //o prace
            case Job::WORK_TYPE_CONTRACT_WORK:
                $jobType = 'contract';
                break; //o dzielo
            case Job::WORK_TYPE_ASSIGN:
                $jobType = 'zlecenie';
                break; // zlecenie
            case Job::WORK_TYPE_SELF:
                $jobType = 'selfemployment';
                break; // inna
            case Job::WORK_TYPE_OTHER:
                $jobType = 'other';
                break; // dowolna
        }

        /** Show application link in offer description, show salary in offer **/
        !empty($opt) && isset($opt["ss"]) ? $show_salary = true : $show_salary = false;
        !empty($opt) && isset($opt["sl"]) ? $show_aplink = true : $show_aplink = false;

        $desc = $this->renderDescription(Portal::OLX_ID, $offerInfo, $opt, false);

        /** Posting form in normal free of charge categories */

        if ($this->_postInDefault) {
            $mainCat = '619'; //pozostałe
            $jobCat  = '405'; //pozostale uslugi '625' , 405 moda
        } else {
            $mainCat = '1443'; //praca
            $jobCat  = $category_ref; //kategoria profession
        }

        $result = $this->sendGet($postOfferUrl);
//        $result = $this->sendHttpRequest($postOfferUrl, null, 'GET')->content;

        $t          = explode('name="data[adding_key]" value="', $result);
        $t          = explode('" />', $t[1]);
        $adding_key = $t[0];

        if ($adding_key != '') {
            //$postOfferUrl = 'http://www.olx.pl/nowe-ogloszenie/confirmpage/hyVrr/?track[new_ad]=1';


            $params = [
                'data[title]'       => $offerInfo->name,
                'data[category_id]' => $jobCat,

                'data[offer_seek]'         => '',//'offer', //I'm offering a job,
                'data[param_type]'         => isset($contract) ? $contract : 'fulltime',
                'data[param_contract]'     => isset($jobType) ? $jobType : 'part',
                'data[param_requirements]' => 'no',

                'data[param_price][0]'         => 'exchange',
                'data[param_state]'            => 'used',
                'data[private_business]'       => 'private',
                'data[description]'            => $desc,
                'data[riak_key]'               => '',
                'image[1]'                     => '',
                'image[2]'                     => '',
                'image[3]'                     => '',
                'image[4]'                     => '',
                'image[5]'                     => '',
                'image[6]'                     => '',
                'image[7]'                     => '',
                'image[8]'                     => '',
                'data[gallery_html]'           => '',
                'data[city_id]'                => '8959',
                'data[city]'                   => 'Kraków, Stare Miasto',//$recruitment->city,//'Kraków, Stare Miasto',
                'data[district_id]'            => '273',
                'loc-option'                   => 'loc-opt-2',
                'data[map_zoom]'               => '13',
                'data[map_lat]'                => '50.06026',
                'data[map_lon]'                => '19.93960',
                'data[person]'                 => 'Tester Tester',//$fullname,
                'data[email]'                  => $this->_loginOlx,
                //date[email] - Have to be the same as login!!!!!!!!!! $email != '' ? $email : $this->_loginOlx,
                'data[phone]'                  => '',//$phone != '' ? $phone : '',
                'data[gg]'                     => '',
                'data[skype]'                  => '',
                'data[payment_code]'           => '',
                'data[liberty]'                => '',
                'data[sms_number]'             => '',
                'data[adding_key]'             => $adding_key,
                'paidadFirstPrice'             => '',
                'paidadChangesLog'             => '',
                'data[suggested_categories][]' => '753',
                'data[suggested_categories][]' => '1191',
                'data[map_radius]'             => '0',
                'data[accept]'                 => '1',
                //'data[payment_code]' => null, //paypack3
                //'data[liberty]' => null

            ]/*, [
              'Access-Control-Allow-Origin:',
              'Content-Type:multipart/form-data',
              'User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
              'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,/;q=0.8',
              'Host:www.olx.pl',
              'Origin:http://olx.pl',
              'Referer:http://olx.pl/nowe-ogloszenie/',
              'location:http://olx.pl/mojolx/moderated/',
              //'Redirect:http://www.olx.pl/nowe-ogloszenie/confirmpage/hyVrr/?track[new_ad]=1',
              //'location:http://olx.pl/mojolx/moderated/',
              //'location:http://www.olx.pl/nowe-ogloszenie/confirmpage/hyVrr/?track[new_ad]=1',
              'Upgrade-Insecure-Requests:1',
              ])*/
            ;

//            //TODO: TO CHANGE:
//            $params = [
//                'data[title]'       => 'aa asd asd asdasa',
//                'data[category_id]' => '405',
//
//                'data[offer_seek]'         => 'offer', //I'm offering a job,
//                'data[param_type]'         => 'fulltime',
//                'data[param_contract]'     => 'part',
//                'data[param_requirements]' => 'no',
//
//                'data[param_price][0]'         => 'exchange',
//                'data[param_state]'            => 'used',
//                'data[private_business]'       => 'private',
//                'data[description]'            => 'a a a a sda das dasd  sad ads a',
//                'data[riak_key]'               => '',
//                'image[1]'                     => '', 'image[2]' => '', 'image[3]' => '', 'image[4]' => '',
//                'image[5]'                     => '',
//                'image[6]'                     => '', 'image[7]' => '', 'image[8]' => '', 'data[gallery_html]' => '',
//                'data[city_id]'                => '8959',
//                'data[city]'                   => '',//$recruitment->city,//'Kraków, Stare Miasto',
//                'data[district_id]'            => '273',
//                'loc-option'                   => 'loc-opt-2',
//                'data[map_zoom]'               => '13',
//                'data[map_lat]'                => '50.06026',
//                'data[map_lon]'                => '19.93960',
//                'data[person]'                 => 'aaaaaa aaaa',
//                'data[email]'                  => $login,
//                //date[email] - Have to be the same as login!!!!!!!!!! $email != '' ? $email : $this->_loginOlx,
//                'data[phone]'                  => '',
//                'data[gg]'                     => '',
//                'data[skype]'                  => '',
//                'data[payment_code]'           => '',
//                'data[sms_number]'             => '',
//                'data[adding_key]'             => $adding_key,
//                'paidadFirstPrice'             => '',
//                'paidadChangesLog'             => '',
//                'data[suggested_categories][]' => '753', //'data[suggested_categories][]' => '1191',
//                'data[map_radius]'             => '0',
//                'data[accept]'                 => '1'
//                //'data[payment_code]' => null, //paypack3
//                //'data[liberty]' => null
//            ];

            $headers = [
                'Access-Control-Allow-Origin:*',
                'Content-Type:multipart/form-data',
                'User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,/*;q=0.8',
                'Host:www.olx.pl',
                'Origin:http://olx.pl',
                'Referer:http://olx.pl/nowe-ogloszenie/',
                'location:http://olx.pl/mojolx/moderated/',
                //'Redirect:http://www.olx.pl/nowe-ogloszenie/confirmpage/hyVrr/?track[new_ad]=1',
                //'location:http://olx.pl/mojolx/moderated/',
                //'location:http://www.olx.pl/nowe-ogloszenie/confirmpage/hyVrr/?track[new_ad]=1',
                'Upgrade-Insecure-Requests:1',
            ];

            $header2 = [
                'Access-Control-Allow-Origin:*',
                'Content-Type:multipart/form-data',
                'User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,/*;q=0.8',
                'Host:www.olx.pl',
                'Origin:http://olx.pl',
                'Referer:http://olx.pl/nowe-ogloszenie/',
                'location:http://olx.pl/mojolx/moderated/',
                //'Redirect:http://www.olx.pl/nowe-ogloszenie/confirmpage/hyVrr/?track[new_ad]=1',
                //'location:http://olx.pl/mojolx/moderated/',
                //'location:http://www.olx.pl/nowe-ogloszenie/confirmpage/hyVrr/?track[new_ad]=1',
                'Upgrade-Insecure-Requests:1',
            ];

            ob_clean();
            $output = $this->sendPost($postOfferUrl, $params, $headers);

            //$output = $this->sendGet('http://www.olx.pl/mojolx/');
            //$output = $this->sendGet('http://www.olx.pl/nowe-ogloszenie/confirmpage/hzDKH//?track%5Bnew_ad%5D=1');
            //$output = $this->sendGet('http://www.olx.pl/nowe-ogloszenie/confirmpage/hzDKH//?track%5Bnew_ad%5D=1');

            //var_dump($this->getCurlInfo());
            //var_dump(sizeof($output));
            //echo $output;
            //die();
        } else {
            return [
                'status'  => 'error',
                'message' => Yii::t('app', 'Błąd podczas publikowania. Brakujące pole lub blokada konta.'),
            ];
        }

        $redirectListUrl = 'https://www.olx.pl/mojolx/waiting/';
        //$redirectListUrl = $this->getCurlInfo()["redirect_url"];

        /* 404 or output got errors */
        if (/*(*/
            $this->getCurlCode() != 404/* && $output == "") || $output != ""*/
        ) {
            /* Olx after posting moves permamenttly */
            if (($this->getCurlCode() == 200 && $output != "") || $this->getCurlCode() == 301 || ($this->getCurlCode() == 302 && $output == "")) {
                /* Push succesfully added offer */
                $feedbackLink = $this->getAnnouncementLink('olx', $redirectListUrl, $mainCat, $offerInfo->title);
                isset($feedbackLink) ? '' : $feedbackLink = '#';

                return ['status' => 'success', 'message' => 'Ok', 'code' => 'posted', 'link' => $feedbackLink];

            } else {
                return [
                    'status'  => 'error',
                    'message' => Yii::t('app', 'Błąd podczas publikowania. Brakujące pole lub blokada konta.'),
                ];
            }

        } else {
            //$response = PostingAppHelper::checkErrors($this->getCurlCode());
            $response = Yii::t('app', 'Nie znaleziono. Konto prawdopodobnie dezaktywowane');

            return ['status'  => 'error',
                    'message' => Yii::t('app', 'Url oferty') . ': ' . $response];
        }

    }

    /**
     * Posts offer Gumtree
     * @param $postOfferUrl
     * @return array
     */
    public function postOfferGumtree($postOfferUrl, $offerInfo, $createdBy, $opt = [])
    {
        $login       = $this->logintoGumtree();
        $recruitment = $offerInfo->recruitment;

        $ppcategory   = new ProfessionPortalCategory();
        $ppref        = $ppcategory->FindByProfessionAndPortal($recruitment->profession_id, 2);
        $category_ref = $ppref->getPortalCategory()->one()->ref_id;

        ($createdBy["first_name"] != NULL && $createdBy["last_name"] != NULL)
            ? $fullname = $createdBy["first_name"] . ' ' . $createdBy["last_name"]
            : $fullname = $createdBy["email"];

        /** Show application link in offer description, show salary in offer **/
        !empty($opt) && isset($opt["ss"]) ? $show_salary = true : $show_salary = false;
        !empty($opt) && isset($opt["sl"]) ? $show_aplink = true : $show_aplink = false;

        $desc = $this->renderDescription(Portal::GUMTREE_ID, $offerInfo, $opt);

        if (isset($offerInfo->contract_id)) {
            $contract = $offerInfo->contract_id;
        } else if (isset($offerInfo->recruitment->contract)) {
            $contract = $offerInfo->recruitment->contract;
        } else {
            $contract = '6';
        }

        if (isset($offerInfo->work_type_id)) {
            $jobType = $offerInfo->work_type_id;
        } else if (isset($offerInfo->recruitment->work_type_id)) {
            $jobType = $offerInfo->recruitment->work_type_id->value;
        } else {
            $jobType = '4';
        };

        switch ($jobType) {
            //case 0: //dowolne
            case Job::WORK_TYPE_FULL_TIME:
                $jobType = 'empcontract';
                break; //o prace
            case Job::WORK_TYPE_ASSIGN:
                $jobType = 'comcontract';
                break; // zlecenie
            case Job::WORK_TYPE_CONTRACT_WORK:
                $jobType = 'contractwork';
                break; //o dzielo
            case Job::WORK_TYPE_OTHER:
                $jobType = 'othr';
                break; // inna
        }

        switch ($contract) {
            //case 0:
            case Job::CONTRACT_FULL:
                $contract = 'fulltime';
                break;
            case Job::CONTRACT_HALF:
                $contract = 'parttime';
                break;
            //case 3:
            //$contract = 'parttime';
            //break;
            case Job::CONTRACT_OTHER:
                $contract = 'graduate';
                break;
        };

        /** Posting form in normal free of charge categories */
        if ($this->_postInDefault) {
            $jobCat     = '9758'; //szukam korek
            $work_array = [];
        } else {
            $jobCat     = $category_ref; //9099 '9105';//
            $work_array = [
                'AdvertisedBy' => 'private',
                'JobType'      => $jobType,
                'ContractType' => $contract,
            ];
        }

        //$this->logintoGumtree();
        $output = $this->sendPost($postOfferUrl, array_merge([
            'locationId'             => '3200208',
            'categoryId'             => $jobCat,
            'machineId'              => 'ee5ebabf-6d21-44eb-84c2-62619b490fca-155720bd126',
            'completenessPercentage' => '50',
            'Title'                  => $offerInfo->name,
            'Description'            => $desc,
            'Email'                  => $this->_loginGumtree, //$email
            'UserName'               => $fullname,
            'Phone'                  => '',
            'latitude'               => '',
            'longitude'              => '',
            'adminAreaName'          => '',
            'addressConfidenceLevel' => '',
            'countryCode'            => '',
            'street'                 => '',
            'Address'                => '',
        ], $work_array), [
            'X-Requested-With: XMLHttpRequest',
            'application/x-www-form-urlencoded',
            'Expect:  ',
        ]);

        $feedbackLink = $this->getAnnouncementLink('gumtree', $this->getCurlInfo()["redirect_url"], null, null);

        /* Gumtree redirects (302) after posting */
        if ($this->getCurlCode() != 200 && $this->getCurlCode() != 301 && $this->getCurlCode() != 302)
            return ['status' => 'error', 'message' => PostingAppHelper::checkErrors($this->getCurlCode()),
                    'code'   => 'posted'];

        if ($output == "") {
            if ($postOfferUrl != "") {
                return ['status' => 'success', 'message' => 'Ok', 'code' => 'posted', 'link' => $feedbackLink];
            } else {
                return ['status' => 'error', 'message' => Yii::t('app', 'Błąd podczas publikowania. Zły url.')];
            }
        } else {
            return ['status'  => 'error',
                    'message' => Yii::t('app', 'Błąd podczas publikowania. Brakujące pole lub blokada konta.')];
        }
    }

    /**
     * Posts offer Lento
     * @param $postOfferUrl
     * @param $offerInfo
     * @return array
     */
    public function postOfferLento($postOfferUrl, $offerInfo, $createdBy, $opt = [])
    {

        $this->logintoLento();

        $recruitment = $offerInfo->recruitment;

        $ppcategory   = new ProfessionPortalCategory();
        $ppref        = $ppcategory->FindByProfessionAndPortal($recruitment->profession_id, 3);
        $category_ref = $ppref->getPortalCategory()->one()->ref_id;

//        ($createdBy["first_name"] != NULL && $createdBy["last_name"] != NULL)
//            ? $fullname = $createdBy["first_name"] . ' ' . $createdBy["last_name"]
//            : $fullname = $createdBy["email"];'

        $fullname = isset($recruitment->createdBy->fullName) ? $recruitment->createdBy->fullName : $this->_loginLento;


        /** Show application link in offer description, show salary in offer **/
        !empty($opt) && isset($opt["ss"]) ? $show_salary = true : $show_salary = false;
        !empty($opt) && isset($opt["sl"]) ? $show_aplink = true : $show_aplink = false;

        $desc = $this->renderDescription(Portal::LENTO_ID, $offerInfo, $opt);

        if (isset($offerInfo->contract_id)) {
            $contract = $offerInfo->contract_id;
        } else if (isset($offerInfo->recruitment->contract)) {
            $contract = $offerInfo->recruitment->contract;
        } else {
            $contract = '6';
        }

        $client = $recruitment->client !== null ? $recruitment->client->name : 'nie podano';
        $email  = $createdBy["email"];

        if (isset($offerInfo->work_type_id)) {
            $jobType = $offerInfo->work_type_id;
        } else if (isset($offerInfo->recruitment->work_type_id)) {
            $jobType = $offerInfo->recruitment->work_type_id->value;
        } else {
            $jobType = '4';
        }


        /** Posting form in normal free of charge categories */
        if ($this->_postInDefault) {
            $jobCat = '41'; //przyjmę za darmo
            // Inne
            $types = [
                'atrr_11_2' => '1',
                'atrr_11_3' => '1',
                'subcatid'  => $jobCat,
            ];
        } else {
            $jobCat = $category_ref;
            $types  = [
                'atrr_11_2' => $contract,
                'atrr_11_3' => $jobType,
                'subcatid'  => '11',//dam prace
                'atrr_11_1' => $jobCat,
            ];
        }

        $params = array_merge([
            'do'      => 'post',
            'adtitle' => $offerInfo->name],
            $types,
            [
                'atrr_6_1'   => '1',
                'atrr_6_2'   => '',
                'atrr_6_3'   => '0',
                'atrr_7_1'   => '1',
                'atrr_7_2'   => '',
                'atrr_8_1'   => '1',
                'atrr_8_2'   => '',
                'atrr_10_1'  => '1',
                'atrr_128_1' => '0',
                'atrr_128_2' => '1',
                'atrr_2_1'   => '0',
                'atrr_2_2'   => '0',
                'atrr_2_3'   => '',
                'atrr_1_1'   => '0',
                'atrr_1_2'   => '0',
                'atrr_1_3'   => '0',
                'atrr_1_4'   => '1',
                'atrr_3_1'   => '0',
                'atrr_3_2'   => '1',
                'atrr_3_3'   => '0',
                'atrr_126_1' => '0',
                'atrr_126_2' => '1',
                'atrr_106_1' => '0',
                'atrr_124_1' => '0',
                'atrr_124_2' => '0',
                'atrr_124_3' => '0',
                'atrr_124_4' => '0',
                'atrr_125_1' => '0',
                'atrr_125_2' => '0',
                'atrr_125_3' => '0',
                'atrr_127_1' => '0',
                'atrr_127_2' => '1',
                'atrr_127_3' => '0',
                'atrr_127_4' => '0',
                'atrr_123_1' => '0',
                'atrr_48_1'  => '0',
                'atrr_48_2'  => '1',
                'atrr_81_1'  => '1',
                'atrr_81_2'  => '1',
                'atrr_82_1'  => '0',
                'atrr_82_2'  => '1',
                'atrr_84_1'  => '0',
                'atrr_84_2'  => '1',
                'atrr_85_1'  => '1',
                'atrr_85_2'  => '1',
                'atrr_86_1'  => '1',
                'atrr_86_2'  => '1',
                'atrr_69_1'  => '0',
                'atrr_69_2'  => '1',
                'atrr_70_1'  => '1',
                'atrr_71_1'  => '1',
                'atrr_72_1'  => '1',
                'atrr_47_1'  => '0',
                'atrr_47_2'  => '1',
                'atrr_50_1'  => '0',
                'atrr_50_2'  => '1',
                'atrr_49_1'  => '0',
                'atrr_49_2'  => '1',
                'atrr_61_1'  => '0',
                'atrr_61_2'  => '1',
                'atrr_89_1'  => '0',
                'atrr_89_2'  => '1',
                'atrr_62_1'  => '0',
                'atrr_60_1'  => '0',
                'atrr_60_2'  => '1',
                'atrr_94_1'  => '0',
                'atrr_94_2'  => '1',
                'atrr_75_1'  => '0',
                'atrr_75_2'  => '1',
                'atrr_51_1'  => '0',
                'atrr_51_2'  => '1',
                'atrr_130_1' => '0',
                'type'       => '0',
                'employer'   => $client,
                'addesc'     => $desc,
                'forfree'    => '1',
                'pic'        => '',
                'location'   => 'Kraków, Małopolskie',
                'locationid' => '19482',
                //'townid' => '55521',
                'townid'     => '',
                'email'      => $this->_loginLento,
                'seller'     => '1',//1 - prywatna, 2 - firma
                'surname'    => $fullname,
                'phone'      => '',
                'agree'      => '1',
                'oferteo'    => '1',
            ]);

        //send req
        $output = $this->sendPost($postOfferUrl, $params, [
            'Expect:  ',
            'Host:www.lento.pl',
            'Origin:http://www.lento.pl',
            'Referer:http://www.lento.pl/dodaj-ogloszenie.html',
        ]);

        if ($this->getCurlCode() != 302) { //cuz redirect to verify
            return [
                'status'  => 'error',
                'message' => PostingAppHelper::checkErrors($this->getCurlCode()),
                'code'    => null,
            ];
        } else {
            if ($output == "") {
                //now verify
                $output_verify = $this->sendPost('http://www.lento.pl/dodaj-ogloszenie.html?step=activate', [
                    'step' => 'activate',
                ], [
                    'Expect:  ',
                    'Host'    => 'www.lento.pl',
                    'Referer' => 'http://www.lento.pl/dodaj-ogloszenie.html?step=activate',
                ]);

                $fbk_msg_pattern = '~<div class="ticksuccess"><h4><span class="ticko"></span>(.*?)</h3><ul><li>~';

                preg_match_all(
                    $fbk_msg_pattern,
                    (string)$output_verify,
                    $feedback
                );

                if (isset($feedback[1][0]) && sizeof($feedback[1][0]) > 0) {
                    $msg  = $feedback[1][0];
                    $code = 'posted';
                } else {
                    //$act_msg_pattern ='~Sprawdź teraz pocztę e-mail. Twoje ogłoszenie czeka na aktywacje.(.*?) e-mail.';
                    $act_msg_pattern = '~Wysłaliśmy link aktywacyjny na adres: <b>(.*?)</b>~';
                    //$act_msg_pattern .= 'Aby aktywować ogłoszenie, wystarczy kliknąć link aktywacyjny w wiadomości e-mail.';

                    preg_match_all(
                        $act_msg_pattern,
                        (string)$output_verify,
                        $feedback
                    );

                    $code = 'activate';
                }

                $feedbackLink = $this->getAnnouncementLink('lento', $this->getCurlInfo()["redirect_url"], null, $offerInfo->name);

                (isset($feedback[1][0]) && sizeof($feedback[1][0]) > 0) ? $msg = $feedback[1][0] : $msg = '';
//
//                if ($this->getCurlCode() == 200 && $feedback[1][0] != "") {
                if (($code = $this->getCurlCode()) == 200 && !empty($feedbackLink)) {
                    return [
                        'status'  => 'success',
                        'message' => htmlspecialchars_decode($msg), 'code' => $code,
                        'link'    => $feedbackLink,
                    ];
                } else {
                    return ['status'  => 'error',
                            'message' => Yii::t('app', 'Błąd podczas publikowania. Wykryte braki.')];
                }
            } else {
                return ['status'  => 'error',
                        'message' => Yii::t('app', 'Błąd podczas publikowania. Brakujące pole lub blokada konta.')];
            }
        }
    }

    /**
     * Posts offer Gazeta praca
     * @param $postOfferUrl
     * @return array
     */
    public function postOfferGazetapraca($postOfferUrl, $offerInfo, $createdBy, $opt = [])
    {
        $this->logintoGazetapraca();
        $recruitment  = $offerInfo->recruitment;
        $ppcategory   = new ProfessionPortalCategory();
        $ppref        = $ppcategory->FindByProfessionAndPortal($recruitment->profession_id, 4);
        $category_ref = $ppref->getPortalCategory()->one()->ref_id;


        ($createdBy["first_name"] != NULL && $createdBy["last_name"] != NULL)
            ? $fullname = $createdBy["first_name"] . ' ' . $createdBy["last_name"]
            : $fullname = $createdBy["email"];

        /** Show application link in offer description, show salary in offer **/
        !empty($opt) && isset($opt["ss"]) ? $show_salary = true : $show_salary = false;
        !empty($opt) && isset($opt["sl"]) ? $show_aplink = true : $show_aplink = false;

        $desc = $this->renderDescription(Portal::GAZETAPRACA_ID, $offerInfo, $opt);

        /** Posting form in normal free of charge categories */
        if ($this->_postInDefault) {
            $jobCat = '41'; //przyjmę za darmo
        } else {
            $jobCat = $category_ref;
        }

        $output = $this->sendPost($postOfferUrl, [], []);

        if ($this->getCurlCode() != 302) { //cuz redirect to verify
            return ['status' => 'error', 'message' => PostingAppHelper::checkErrors($this->getCurlCode())];
        } else {
            if ($output == "") {
                if ($this->getCurlCode() == 200) {
                    $msg = 'ok';

                    return ['status' => 'success', 'message' => $msg, 'code' => 'posted'];
                } else {
                    return ['status' => 'error', 'message' => 'Błąd podczas publikowania. Wykryto braki.'];
                }
            } else {
                return ['status'  => 'error',
                        'message' => 'Błąd podczas publikowania. Brakujące pole lub blokada konta.'];
            }
        }
    }

    /**
     * Posts offer Goldenline
     * @param $postOfferUrl
     * @return array
     */
    public function postOfferGoldenline($postOfferUrl, $offerInfo, $createdBy, $opt = [])
    {
        $this->logintoGoldenline();
        $recruitment  = $offerInfo->recruitment;
        $ppcategory   = new ProfessionPortalCategory();
        $ppref        = $ppcategory->FindByProfessionAndPortal($recruitment->profession_id, 5);
        $category_ref = $ppref->getPortalCategory()->one()->ref_id;

        ($createdBy["first_name"] != NULL && $createdBy["last_name"] != NULL)
            ? $fullname = $createdBy["first_name"] . ' ' . $createdBy["last_name"]
            : $fullname = $createdBy["email"];

        $client = $recruitment->client !== null ? $recruitment->client->name : 'nie podano';
        $email  = $createdBy["email"];
        $phone  = $createdBy["phone"];

        /** Show application link in offer description, show salary in offer **/
        !empty($opt) && isset($opt["ss"]) ? $show_salary = true : $show_salary = false;
        !empty($opt) && isset($opt["sl"]) ? $show_aplink = true : $show_aplink = false;

        $desc = $this->renderDescription(Portal::GOLDENLINE_ID, $offerInfo, $opt);

        /** Posting form in normal free of charge categories */
        if ($this->_postInDefault) {
            $jobCat = '16'; //inne
        } else {
            $jobCat = $category_ref;
        }

        //Step 1
        $output = $this->sendPost($postOfferUrl, [
            //'data[description]'            => $desc,

            'branch_helper' => $jobCat,
            'company_name'  => $fullname,
            'email'         => $email,//$this->_loginGoldenline,
            'phone'         => $phone,
            'position_name' => $offerInfo->name,
            'city'          => isset($client->city) ? $client->city : '',
            'template'      => '1',

        ], [
            'Host:panel.goldenline.pl',
            'Referer:https://panel.goldenline.pl/ecommerce/offer_change/add_offer/14/first-step/',
            'User-Agent:Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',

        ]);

        //Step 2
        $output = $this->sendPost('https://panel.goldenline.pl/ecommerce/offer_change/add_offer/14/second-step/', [
            //'data[description]'            => $desc,

            'additional_informations'  => '',
            'company_description'      => '',
            'confidential_clause'      => 'on', //przetw. danych
            'offer_description'        => $desc,
            'position_description'     => $desc,
            'reference_number'         => '',
            'region'                   => '6',//malopolskie
            'requirements_description' => '',

        ], [
            'Host:panel.goldenline.pl',
            'Referer:https://panel.goldenline.pl/ecommerce/offer_change/add_offer/14/second-step/',
            'User-Agent:Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',

        ]);

        //Step 3
        if (!empty($offerInfo->main_link)) {
            $attrs = [
                'applying_email' => '',
                'applying_form'  => 'W',
                'applying_www'   => $offerInfo->main_link,
                'extra_emails'   => '',
                'start_date'     => date("Y-m-d"),

            ];
        } else {
            $attrs = [
                'applying_email' => $email,//$this->_loginGoldenline,
                'applying_form'  => 'E',
                'extra_emails'   => '',
                'start_date'     => date("Y-m-d"),

            ];
        }
        $output = $this->sendPost('https://panel.goldenline.pl/ecommerce/offer_change/add_offer/14/third-step/', $attrs, [
            'Host:panel . goldenline . pl',
            'Referer:https://panel.goldenline.pl/ecommerce/offer_change/add_offer/14/third-step/',
            'User-Agent:Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',

        ]);

        if ($this->getCurlCode() != 302) { //cuz redirect to verify
            return ['status' => 'error', 'message' => PostingAppHelper::checkErrors($this->getCurlCode())];
        } else {
            if ($output == "") {
                if ($this->getCurlCode() == 200) {
                    $msg = 'ok';

                    return ['status' => 'success', 'message' => $msg, 'code' => 'posted'];
                } else {
                    return ['status' => 'error', 'message' => 'Błąd podczas publikowania. Wykryto braki.'];
                }
            } else {
                return ['status'  => 'error',
                        'message' => 'Błąd podczas publikowania. Brakujące pole lub blokada konta.'];
            }
        }
    }

    /**
     * Posts offer Info praca
     * @param $postOfferUrl
     * @return array
     * register at http://www.infopraca.pl/employer/register/confirmation
     */
    public function postOfferInfopraca($postOfferUrl, $offerInfo, $createdBy, $opt = [])
    {
        $this->logintoInfopraca();
        $recruitment  = $offerInfo->recruitment;
        $ppcategory   = new ProfessionPortalCategory();
        $ppref        = $ppcategory->FindByProfessionAndPortal($recruitment->profession_id, 6);
        $category_ref = $ppref->getPortalCategory()->one()->ref_id;

        ($createdBy["first_name"] != NULL && $createdBy["last_name"] != NULL)
            ? $fullname = $createdBy["first_name"] . ' ' . $createdBy["last_name"]
            : $fullname = $createdBy["email"];

        $client = $recruitment->client !== null ? $recruitment->client->name : 'nie podano';
        $email  = $createdBy["email"];
        $phone  = $createdBy["phone"];

        /** Show application link in offer description, show salary in offer **/
        !empty($opt) && isset($opt["ss"]) ? $show_salary = true : $show_salary = false;
        !empty($opt) && isset($opt["sl"]) ? $show_aplink = true : $show_aplink = false;

        $min_school = '';
        $jobType    = '';
        $contr      = '';

        $exp = $offerInfo->experience_id; //takie same id

        if (isset($offerInfo->education_title_id)) {
            $school = $offerInfo->education_title_id;
        } else if (isset($offerInfo->recruitment->education_title)) {
            $school = $offerInfo->recruitment->education_title;
        } else {
            $school = '1';
        }

        if (isset($offerInfo->contract_id)) {
            $contr = $offerInfo->contract_id;
        } else if (isset($offerInfo->recruitment->contract)) {
            $contr = $offerInfo->recruitment->contract;
        } else {
            $contr = '1';
        }

        if (isset($offerInfo->work_type_id)) {
            $jobType = $offerInfo->work_type_id;
        } else if (isset($offerInfo->recruitment->work_type_id)) {
            $jobType = $offerInfo->recruitment->work_type_id->value;
        } else {
            $jobType = '1';
        }

        switch ($school) {
            case 1:
                $min_school = "1";
                break;
            case 2:
                $min_school = "2";
                break;
            case 3:
                $min_school = "2"; //info praca nie ma gim
                break;
            case 4:
                $min_school = "4"; //info praca nie ma gim
                break;
            case 5:
                $min_school = "21";
                break;
            case 6:
                $min_school = "21"; //nie ma technikum
                break;
            case 7:
                $min_school = "9";
                break;
            case 8: //inż
                $min_school = "26";
                break;
            case 9: //mgr
                $min_school = "24";
                break;
            case 10: //podypl
                $min_school = "25";
                break;
            case 11: //dokto
                $min_school = "12";
                break;
        }
        //"5" średnie zawodowe
        //"23">Policealne</option>

        /**
         * 1 - o prace
         * 3 - dzielo
         * 4 - zlecenie
         * 5 - samozat
         * 6 - inne
         * 7 - obojetne
         *
         */
        switch ($jobType) {
            case Job::WORK_TYPE_FULL_TIME:
                $jobType = 1; //czas okreslony
                break;
            case Job::WORK_TYPE_CONTRACT_WORK:
                $jobType = 9; //dzielo
                break;
            case Job::WORK_TYPE_ASSIGN:
                $jobType = 10; //zlecenie
                break;
            case Job::WORK_TYPE_SELF:
                $jobType = 5; //freelancer
                break;
            case Job::WORK_TYPE_TEMPORARY:
                $jobType = 12; //próbny
                break;
            case 7:
                $jobType = 1;
                break;

        }
        /**
         * 1 - dowolne
         * 2 - pełen etat
         * 3 - pol etatu
         * 4 - czesc etatu
         * 5 - pratyka/staz
         */
        switch ($contr) {
            case Job::CONTRACT_OTHER:
                $contr = '11';
                break;
            case Job::CONTRACT_FULL:
                $contr = '0';
                break;
            case Job::CONTRACT_HALF:
                $contr = '3';
                break;
        }

        $desc = $this->renderDescription(Portal::INFOPRACA_ID, $offerInfo, $opt);

        /** Posting form in normal free of charge categories */
        if ($this->_postInDefault) {
            $jobCat = '41'; //przyjmę za darmo
        } else {
            $jobCat = $category_ref;
        }

        $output = $this->sendPost($postOfferUrl, [
            //'categoryId' => $jobCat,
            'position_title'            => $offerInfo->name,
            'vacancy_count'             => '1',
            'subregion'                 => '6', //malopolskie
            'municipality'              => 'Kraków', //$recruitment->city
            'post_board_categories'     => $jobCat,
            'job_responsibilities'      => isset($recruitment->description) ? $recruitment->description : '...',
            'required_skills'           => isset($recruitment->description) ? $recruitment->description : '...',
            'required_experience_level' => $exp,
            'degree_type'               => $min_school,
            'position_classification'   => $contr,
            'position_schedule'         => $jobType,
            'Email'                     => $this->_loginInfopraca, //$createdBy["email"]
            'UserName'                  => $fullname,
        ], [
            'X-Requested-With: XMLHttpRequest',
            'application/x-www-form-urlencoded',
            'Expect:  ',
            'Host:www.infopraca.pl',
        ]);

        /* Gumtree redirects (302) after posting */
        if ($this->getCurlCode() != 200 && $this->getCurlCode() != 302)
            return ['status' => 'error', 'message' => PostingAppHelper::checkErrors($this->getCurlCode()),
                    'code'   => 'posted'];

        if ($output == "") {
            if ($postOfferUrl != "") {
                return ['status' => 'success'];
            } else {
                return ['status' => 'error', 'message' => 'Błąd podczas publikowania. Zły url.'];
            }
        } else {
            return ['status' => 'error', 'message' => 'Błąd podczas publikowania. Brakujące pole lub blokada konta.'];
        }
    }

    /**
     * Posts offer Pracuj.pl
     * @param $postOfferUrl
     * @return array
     */
    public function postOfferPracujpl($postOfferUrl, $offerInfo, $createdBy, $opt = [])
    {
        $this->logintoPracujpl();
        $recruitment  = $offerInfo->recruitment;
        $ppcategory   = new ProfessionPortalCategory();
        $ppref        = $ppcategory->FindByProfessionAndPortal($recruitment->profession_id, 7);
        $category_ref = $ppref->getPortalCategory()->one()->ref_id;

        ($createdBy["first_name"] != NULL && $createdBy["last_name"] != NULL)
            ? $fullname = $createdBy["first_name"] . ' ' . $createdBy["last_name"]
            : $fullname = $createdBy["email"];

        $client = $recruitment->client !== null ? $recruitment->client->name : 'nie podano';
        $email  = $createdBy["email"];
        $phone  = $createdBy["phone"];

        /** Show application link in offer description, show salary in offer **/
        !empty($opt) && isset($opt["ss"]) ? $show_salary = true : $show_salary = false;
        !empty($opt) && isset($opt["sl"]) ? $show_aplink = true : $show_aplink = false;

        $desc         = $this->renderDescription(Portal::PRACUJPL_ID, $offerInfo, $opt, true);
        $requirements = Yii::t('app', 'Wymagania w opisie');

        /** Posting form in normal free of charge categories */
        if ($this->_postInDefault) {
            $jobCatInput  = '5012';
            $jobCatId     = 'ctl00$DefaultContent$lstNewCategories$34';
            $jobSCatInput = '#5012#5012';
            $jobSCatId    = 'ctl00$DefaultContent$lstNewSubCategories$185';
        } else {
            $jobSubCats   = $this->getPracujCategory($category_ref);
            $jobCatInput  = $jobSubCats["cat_input"];
            $jobCatId     = $jobSubCats["cat_id"];
            $jobSCatInput = $jobSubCats["subcat_input"];
            $jobSCatId    = $jobSubCats["subcat_id"];
        }

        $basketId = '306249';

        /** Run First init step of posting - then next */
        $this->loginto('pracujpl');
        $output0 = $this->runStepInitPracujPl($postOfferUrl);

        $postOfferUrl2 = 'https://sklep.pracuj.pl/Offers/Region.aspx?basketId=' . $basketId . '&cs=A328C2CF1748D1F30E908BB4DF077D7A';
        $output1       = $this->runStepOnePracujPl($postOfferUrl2, $offerInfo->name, $fullname);

        $postOfferUrl2 = 'https://sklep.pracuj.pl/Offers/Contact.aspx?basketId=' . $basketId . '&cs=5C420A6A80DE3C2DE5E8C807036394C6';
        $output2       = $this->runStepTwoPracujPl($postOfferUrl2, $offerInfo->name, $offerInfo->main_link, $offerInfo->email, $recruitment->ref);

        $postOfferUrl2 = 'https://sklep.pracuj.pl/Offers/OfferContent.aspx?basketId=' . $basketId . '&cs=5C420A6A80DE3C2DE5E8C807036394C6';
        $output3       = $this->runStepThreePracujPl($postOfferUrl2, $desc, $requirements);

        $postOfferUrl2 = 'https://sklep.pracuj.pl/Offers/Categories.aspx?basketId=' . $basketId . '=5C420A6A80DE3C2DE5E8C807036394C6';
        $output4       = $this->runStepFourPracujPl($postOfferUrl2, $jobCatId, $jobSCatId, $jobSCatInput, $jobCatInput);

        $postOfferUrl2 = 'https://sklep.pracuj.pl/Orders/OrderSummary.aspx?basketId=' . $basketId . '&cs=5C420A6A80DE3C2DE5E8C807036394C6';
        $output5       = $this->runStepFivePracujPl($postOfferUrl2);

        $output = $output5;
        if ($this->getCurlCode() != 302) { //cuz redirect to verify
            return ['status' => 'error', 'message' => PostingAppHelper::checkErrors($this->getCurlCode()),
                    'code'   => 'posted'];
        } else {
            if ($output == "") {
                if ($this->getCurlCode() == 200) {
                    $msg = 'ok';

                    return ['status' => 'success', 'message' => $msg];
                } else {
                    return ['status' => 'error', 'message' => 'Błąd podczas publikowania. Zły url.'];
                }
            } else {
                return ['status'  => 'error',
                        'message' => 'Błąd podczas publikowania. Brakujące pole lub blokada konta.'];
            }
        }
    }

    /**
     * @param $offerInfo
     * @return array
     * @throws \Exception
     */
    public function postOfferInterimax($offerInfo, $opt)
    {

        return $this->postOfferInternal($offerInfo, Portal::INTERIMAX_ID, $opt);
    }

    /**
     * @param $offerInfo
     * @return array
     * @throws \Exception
     */
    public function postOfferAteam($offerInfo, $opt)
    {
        return $this->postOfferInternal($offerInfo, Portal::ATEAM_ID, $opt);
    }

    /**
     * Internal posting
     * @param Announcement2 $announcement
     * @param $portal_id integer
     * @param $opt
     * @return array
     * @throws ModelNotFoundException
     */
    public function postOfferInternal($announcement, $portal_id, $opt)
    {
        /** @var Portal $portal */
        $portal = Portal::findOne($portal_id);

        if ($portal == null) throw new ModelNotFoundException(Yii::t('app', 'Nie znaleziono takiego portalu'));

        //send request to WordPress with publicated offer
        $postResponse = $this->sendOfferToInternalPortal($announcement, $portal, $opt);

//        $flag = (bool) $postResponse;
//
//        if (!$flag) return [
//            'status'  => 'error',
//            'message' => $postResponse,
//        ];

        $slug         = Inflector::slug($announcement->title);
//        $feedbackLink = Url::to(['frontend/' . $portal->name . '/oferta/' . $slug . '/' . $announcement->hash]);
        $feedbackLink = $postResponse->info->link;

        if (strpos($feedbackLink, "/backend/web") !== false) {
            $replaced     = str_replace("/backend/web", "", $feedbackLink);
            $feedbackLink = trim($replaced);
        }

        return [
            'status'  => 'success',
            'message' => 'Ok',
            'code'    => 'posted',
            'link'    => $feedbackLink,
        ];
    }


    /**
     * Sends get
     * @param $url
     * @param $certificate
     * @param $headers
     * @return mixed
     *
     * cookies.txt REQUIRED!
     * *.pem REQUIRED if SSL!
     */
    protected function sendGet($url, $certificate = false, $headers = false)
    {
        $uid          = Yii::$app->user->identity->id;
        $cookies_file = Yii::getAlias('@runtime') . '/posting/cookies/cookies_user_' . $uid . '.txt';
        if (!file_exists($cookies_file)) {
            $file = fopen($cookies_file, "w");
            fwrite($file, '');
            fclose($file);
        }

        $v = [
            CURL_SSLVERSION_DEFAULT,
            CURL_SSLVERSION_TLSv1,
            CURL_SSLVERSION_SSLv2,
            CURL_SSLVERSION_SSLv3,
            CURL_SSLVERSION_TLSv1_0,
            CURL_SSLVERSION_TLSv1_1,
            CURL_SSLVERSION_TLSv1_2,
        ];

        curl_setopt($this->getCurlHandler(), CURLOPT_URL, $url);
        curl_setopt($this->getCurlHandler(), CURLOPT_POST, false); //!!
        curl_setopt($this->getCurlHandler(), CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->getCurlHandler(), CURLOPT_SSL_VERIFYPEER, false);
        if ($headers) curl_setopt($this->getCurlHandler(), CURLOPT_HTTPHEADER, $headers);
        if ($certificate) curl_setopt($this->getCurlHandler(), CURLOPT_SSL_VERIFYHOST, 2);
        if ($certificate) curl_setopt($this->getCurlHandler(), CURLOPT_RETURNTRANSFER, true);
        if ($certificate) curl_setopt($this->getCurlHandler(), CURLOPT_VERBOSE, true);

        if ($certificate) curl_setopt($this->getCurlHandler(), CURLOPT_CAINFO, Yii::getAlias('@runtime') . '/posting/cacert-2016-09-14.pem');
        curl_setopt($this->getCurlHandler(), CURLOPT_COOKIEJAR, $cookies_file);
        curl_setopt($this->getCurlHandler(), CURLOPT_COOKIEFILE, $cookies_file);

//        if($certificate) curl_setopt($this->getCurlHandler(), CURLOPT_CAINFO, dirname(__FILE__) . '/cacert-2016-09-14.pem');
//        curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__) . '/cookies.txt');
//        curl_setopt($ch, CURLOPT_COOKIEFILE, dirname(__FILE__) . '/cookies.txt');

        curl_setopt($this->getCurlHandler(), CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.2 (KHTML, like Gecko) Chrome/5.0.342.3 Safari/533.2');

        return curl_exec($this->getCurlHandler());
    }

    /**
     * Sends post
     * @param $url
     * @param array $params
     * @param array $headers
     * @return mixed
     */
    protected function sendPost($url, $params = [], $headers = [])
    {
        $uid          = Yii::$app->user->identity->id;
        $cookies_file = Yii::getAlias('@runtime') . '/posting/cookies/cookies_user_' . $uid . '.txt';

        $exists = file_exists($cookies_file);
        if (!$exists) {
            $file = fopen($cookies_file, "w");
            fwrite($file, '');
            fclose($file);
        }

        $ch = $this->getCurlHandler();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies_file);

//        curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__) . '/cookies.txt');
//        curl_setopt($ch, CURLOPT_COOKIEFILE, dirname(__FILE__) . '/cookies.txt');

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.2 (KHTML, like Gecko) Chrome/5.0.342.3 Safari/533.2');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

        //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        return curl_exec($ch);
    }

    /**
     * @return mixed
     */
    protected function getCurlInfo()
    {
        return curl_getinfo($this->getCurlHandler());
    }

    /**
     *
     * After login - update unposted offers
     * if not posted yet, or outdated
     *
     * @param $toPostIds
     * @param $site
     * @param $logF
     * @param array $opt
     * @return array
     */
    public function updateOffers($toPostIds, $site, $logF, $opt = [])
    {

        $msg             = '';
        $dbFbk           = '';
        $status          = 0;
        $st_aft          = 0;
        $posting_message = ['status_after' => '', 'code' => '', 'msg' => ''];
        $result          = ['status' => '', 'message' => '', 'code' => ''];
        $exists          = false;

        $offersDb = Announcement2::find()
            ->joinWith('recruitment.profession')
            ->all();

        /* Foreach offer from $site */
        foreach ($offersDb as $key => $storedOfferToPost) {

            $status_before = '';
            $status_after  = '';

            $id      = isset($storedOfferToPost['id']) ? $storedOfferToPost['id'] : null;
            $posting = isset($id) ? Announcement2::getPostingStatus($site, null, $id) : null;
            $status  = isset($posting) ? $posting["status"] : null;

            //isset($posting) ? $portal_id = $posting["portal_id"] : $portal_id = null;
            $portal_id = $this->getSiteIdByName($site);

            if ((isset($toPostIds) && !in_array($storedOfferToPost->id, $toPostIds, true))
                || (!isset($toPostIds))
                && (isset($id) && isset($posting))
            ) {
                if ((sizeof($toPostIds) == 0 || in_array($id, $toPostIds)) &&
                    ($status != PortalPost::STATUS_POSTED OR ($portal_id == Portal::INTERIMAX_ID OR $portal_id == Portal::ATEAM_ID))
                ) {
                    $result = $this->postOffer($storedOfferToPost, $site, $logF, $opt);
                }

                /** for each offer separately! that was given by checkboxes **/
                if (in_array($id, $toPostIds) && $result["status"] != null) {

                    $posting_message = $this->renderPostingMessage(
                        $storedOfferToPost["name"],
                        $posting["status"],
                        $result["status"],
                        $result["message"],
                        $result["code"],
                        $result["link"]
                    );

                    $link_msg = '<div class="clearfix"><div/>';
                    $link_msg .= Html::a(Yii::t('app', 'Przejdź do oferty pracy'), $result["link"], ['target' => '_blank',
                                                                                                     'class'  => 'btn btn-success no-shaddow text-uppercase',]);
                    $link_msg .= '<br/>';

                    $msg .= $posting_message["msg"] . $link_msg;
                    $status_before = $posting["status"] == null ? 0 : $posting["status"];
                    $posting_message["status_after"] == 1 ? $status_after = $posting_message["code"] : $status_after = PortalPost::STATUS_POSTED;//$result["status"];

                    $portal_info = Portal::findOne($this->getSiteIdByName($site));
                    $min_on_air  = isset($posting["min_onair"]) ? $posting["min_onair"] : $portal_info->min_onair;

                    try {
                        //$this->saveLogMsg($this->_logF, $result["message"]);
                        $this->generateLogMsg($storedOfferToPost["name"], $site, $posting["status"], $result["message"], true);
                        $msg .= $this->setOfferStatusDB($portal_id, $id, $status_before, $status_after, $min_on_air, $result["link"]) . ".<br/>";
                    } catch (Exception $e) {
                        if ($this->_postInDefault)
                            $msg .= $e->getMessage(); //in case print errors
                    }
                    $exists = false;
                } else {
                    $exists = true;
                }

            }

        }

        $exists == true ? $msg .= Yii::t('app', 'Oferta już istnieje na portalu.') : $msg .= '';
        empty($msg) ? $msg .= "<br/>" . Yii::t('app', 'Nic nie zostało opublikowane.') : $msg .= '';

        return ["message" => $msg];
    }

    /**
     * Saves logs intro
     * @param $fileUrl
     */
    public function saveLogIntro($fileUrl)
    {
        $msg     = date('Y-m-d H:i:s') . ": -- Posting script executing start -- \r\n";
        $logFile = fopen($fileUrl, 'a');
        fwrite($logFile, $msg);
        fclose($logFile);
    }

    /**
     * Saves log's message
     * @param $fileUrl
     * @param $msg
     */
    public function saveLogMsg($fileUrl, $msg)
    {
        $logFile = fopen($fileUrl, 'a');
        fwrite($logFile, $msg);
        fclose($logFile);
    }

    /**
     * Saves log's outro
     * @param $fileUrl
     */
    public function saveLogOutro($fileUrl)
    {
        $msg     = date('Y-m-d H:i:s') . ":                 -- ^ -- \r\n";
        $logFile = fopen($fileUrl, 'a');
        fwrite($logFile, $msg);
    }

    /**
     * Generates log's message
     * @param $offerName
     * @param $site
     * @return string
     */
    public function generateLogMsg($offerName, $site, $is_success, $msg, $auto = true)
    {
        if ($auto == true) {
            if ($is_success) {
                $result = 'has been';
            } else {
                $result = 'has not been';
            };

            return date('Y-m-d H:i:s') . ': "' . $offerName . "\" " . $result . " posted ('" . PostingAppHelper::getWebisteName($site) . "')\r\n";
        } else {
            return date('Y-m-d H:i:s') . ': "' . $msg . " ('" . PostingAppHelper::getWebisteName($site) . "')\r\n";
        }
    }

    /**
     * Generates callback for posting actions
     * @param $site
     * @param $logging
     * @param $posting
     * @return string
     */
    public function generateCallback($site, $logging, $posting)
    {

        $model = new Portal();
        $site  = $model->renderSiteRealName()[$site];
        $logging["status"] == 'success' ? $status_lbl = Yii::t('app', 'Sukces') : Yii::t('app', 'Niepowodzenie');

        isset($logging["status"]) ? $res_logg = $status_lbl : $res_logg = Yii::t('app', 'Błąd');
        isset($posting["message"]) ? $res_post = $posting["message"] : $res_post = Yii::t('app', 'Błąd');

        if (!isset($logging["status"])) {
            return "<b>[" . $site . "]</b><br/><b>" . Yii::t('app', 'Logowanie') . "</b>: " . $res_logg . " " . $res_post . "<br/><br/>";
        } else {
            return "<b>[" . $site . "]</b><br/><b>" . Yii::t('app', 'Logowanie') . "</b> : " . $res_logg . " <br/><b>" . Yii::t('app', 'Umieszczanie oferty') . "</b>: <br/>" . $res_post . "<br/><br/>";
        }
    }

    /**
     * Returns info if posting is not available
     * @param $site
     * @return string
     */
    public function generateCallbackOff($site, $turnedon)
    {
        $model = new Portal();
        $site  = isset($model->renderSiteRealName()[$site]) ? $model->renderSiteRealName()[$site] : '';
        if ($turnedon) {
            return "<b>[" . $site . "]</b><br/>" . Yii::t('app', 'Za mały budżet!') . "<br/>";
        } else {
            return "<b>[" . $site . "]</b><br/>" . Yii::t('app', 'Publikowanie wyłączone!') . "<br/>";
        }
    }

    /**
     * Renders posting callback msg
     *
     * @param $returned
     * @param $offerData
     * @param $site
     * @return string
     */
    public function renderPostingCallback($returned, $offerData, $site)
    {
        isset($returned["message"]) ? $stat_err = $returned["message"] : $stat_err = '';
        isset($returned["success"]) ? $stat_ok = $returned["success"] : $stat_ok = '';

        $msg_intro = 'ID#' . $offerData->id . ' - ' . Yii::t('app', 'Status') . ': ';
        $msg_err   = $msg_intro . Yii::t('app', 'Błąd') . ' ' . $stat_err . ' - ' . $returned["message"];
        $msg_suc   = $msg_intro . Yii::t('app', 'Sukces') . ' ' . $stat_ok . ' - ' . $returned["message"];

        if ($returned["status"]) {
            $this->saveAddedOfferState($site, $offerData->id);
        }

        $returned["status"] == "error"
            ? $this->saveLogMsg(
            $this->_logF,
            $this->generateLogMsg($offerData->name, $site, false, $msg_err, false)
        ) : $this->saveLogMsg(
            $this->_logF,
            $this->generateLogMsg($offerData->name, $site, true, $msg_suc, false)
        );

        if ($returned["status"] == "error") {
            $stat     = Yii::t('app', 'Błąd');
            $stat_msg = $msg_err;
        } else {
            $stat     = Yii::t('app', 'Sukces');
            $stat_msg = $msg_suc;
        }

        //$callback = "[" . $stat . "] " .
        return $offerData->name . " - " . $stat_msg . '. ';
    }

    /**
     * Gets latest CURL HTTP code
     * @return mixed
     */
    public function getCurlCode()
    {
        return $this->getCurlInfo()["http_code"];
    }

    /**
     * Checks if user has enough money to post an offer
     * @param $site
     * @return bool
     */
    public function isBalanceOk($site)
    {
        switch ($site) {
            case 'olx':
                return $this->checkBalanceOlx();
                break;
            case 'gumtree':
                return $this->checkBalanceGumtree();
                break;
            case 'lento':
                return $this->checkBalanceLento();
                break;
            case 'gazetapraca':
                return $this->checkBalanceGazetapraca();
                break;
            case 'infopraca':
                return $this->checkBalanceInfopraca();
                break;
            case 'pracujpl':
                return $this->checkBalancePracujpl();
                break;
            case 'interimax':
                return true;
                break;
            case 'ateam':
                return true;
                break;
        }

    }

    /**
     * Search for OLX account balance info and checks it
     * @return bool
     */
    public function checkBalanceOlx()
    {
        $logged = $this->logintoOlx();

        if (isset($logged["status"]) && $logged["status"] == "success") {
            $balanceCount = 0;
            $mainHtml     = $this->sendPost('http://olx.pl/', null, []);
            $balanceHtml  = $this->sendGet('http://olx.pl/mojolx/portfel/');

            /**
             * $b = explode('id="paybalanceDropdownTitle"><span class="br4">', $balanceHtml);
             * if (isset($b[1])) {
             * $b            = explode('zł</span></a>', $b[1]);
             * $balanceCount = (float)trim($b[0]);
             * }
             * var_dump($b[1], $balanceCount);**/
            //<a href="#" class="title br4" id="paybalanceDropdownTitle"><span class="br4">0 zł</span></a>
            $pattern = '~<a href="#" class="title br4" id="paybalanceDropdownTitle"><span class="br4">(.*?) zł</span></a>~';

            preg_match_all(
                $pattern,
                (string)$balanceHtml,
                $feedback
            );
            $balanceCount = $feedback[1][0];

            return $balanceCount < $this->_postingPriceOlx ? false : true;
        } else {
            return false;
        }
    }

    /**
     * Search for Gumtree account balance info and checks it
     * @return bool
     */
    public function checkBalanceGumtree()
    {

        $loginUrl = 'http://www.gumtree.pl/login.html';
        $headers  = [
            'Host:www.gumtree.pl',
            'Origin:https://www.gumtree.pl',
            'Referer: ' . $loginUrl,
            'Upgrade-Insecure-Requests:1',
            'Expect: ',
            'application/xhtml+voice+xml;version=1.2, application/x-xhtml+voice+xml;version=1.2, text/html, application/xml;q=0.9, application/xhtml+xml, image/png, image/jpeg, image/gif, image/x-xbitmap, */*;q=0.1',
            'Connection: Keep-Alive',
            'Content-type: application/x-www-form-urlencoded;charset=UTF-8',
        ];

        //302 found, redirect = ''

        $result = $this->sendPost($loginUrl, [
            'redirect' => 'http://gumtree.pl/',
            'email'    => $this->_loginGumtree,
            'password' => $this->_passGumtree,
        ], $headers);

        $output = $this->sendGet('https://www.gumtree.pl/my/orders.html');

        $pattern = '~<tr class="noDataRow" ><td colspan="3">Brak danych</td></tr>~';

        preg_match_all(
            $pattern,
            (string)$output,
            $feedback
        );

        return ($feedback[0] !== null) ? true : false;
    }

    /**
     * Search for Lento account balance info and checks it
     * @return bool
     */
    public function checkBalanceLento()
    {
        return true; //posting job offers is free
    }

    /**
     * Search for Gazetapraca account balance info and checks it
     * @return bool
     */
    public function checkBalanceGazetapraca()
    {
        return false;
    }

    /**
     * Search for Infopraca account balance info and checks it
     * @return bool
     */
    public function checkBalanceInfopraca()
    {
        return false;
    }

    /**
     * Search for Pracujpl account balance info and checks it
     * @return bool
     */
    public function checkBalancePracujpl()
    {
        return false;
    }

    /**
     * Pushes info about posted offers
     * @param $site
     * @param $id
     */
    public function saveAddedOfferState($site, $id)
    {
        array_push($this->_updatedOffers[$site], $id);
    }

    /**
     * Gets all unposted offers from DB
     * @param $site
     * @return array|null
     */
    public function getNonActiveOffersIds($site)
    {

        //Find offers that haven't been posted yet that have been created by this user
        $query = "SELECT `rp`.`announcement_id` ";
        $query .= "FROM `announcement_posted` AS `rp` LEFT JOIN `announcement` AS `r` ";
        $query .= "ON `r`.id = `rp`.`announcement_id` ";
        $query .= "WHERE `announcement_id` NOT LIKE 0 ";
        $query .= "AND `posted_" . $site . "` LIKE 0 ";
        $query .= "AND `r`.`created_by` LIKE " . Yii::$app->user->id;

        $db      = Yii::$app->db;
        $command = $db->createCommand($query);
        $result  = $command->queryAll();


        if ($result == 0) {
            return NULL;
        } else {
            return $result;
        }
    }

    /**
     * Way too slow. Changing posting $status for offer with ID $offer_id and site name $site
     * Sets offers in DB as posted
     *
     * @param $portal_id
     * @param $announcement_id
     * @param $status_before
     * @param $status_after
     * @param $duration
     * @param null $feedback_link
     * @return string
     */
    public function setOfferStatusDB($portal_id, $announcement_id, $status_before, $status_after, $duration, $feedback_link = null)
    {
        try {
            $portalPost = PortalPost::find()->where(['announcement_id' => $announcement_id, 'portal_id' => $portal_id])
                ->one();

            if (!isset($portalPost)) {
                $portalPost                  = new PortalPost();
                $portalPost->portal_id       = $portal_id;
                $portalPost->announcement_id = $announcement_id;
            }

            $hours = $duration * 24; //days * hours

            $portalPost->status     = $status_after;
            $portalPost->link       = $feedback_link;
            $portalPost->date_start = date("Y-m-d H:i:s");
            $portalPost->date_end   = date('Y-m-d H:i:s', strtotime('+' . $hours . ' hour'));

            $flag = $portalPost->save(false);

            if ($flag) {
                return Yii::t('app', 'Zapisano.');
            } else {
                return Yii::t('app', 'Nie zapisano zmiany statusu!');
            }

        } catch (Exception $e) {
            return Yii::t('app', 'Błąd przy zmienianiu statusu publikacji') . ': <br/>' . $e->getMessage();
        }

    }


    /**
     * Checks outdated
     */
    public static function runOutdatedChecking()
    {
        $post = new Post();
        $post->checkOutdated();
    }

    /**
     * Checking outdated
     */
    private function checkOutdated()
    {
        $this->_logF = dirname(__FILE__) . '/../../runtime/posting/logs/cron_' . date('Y_m_d') . '.txt';

        $logged   = null;
        $posted   = null;
        $callback = null;

        $this->saveLogIntro($this->_logF);

        /* OLX */
        $site_name = 'olx';
        $titles    = $this->getOutdatedOffers($site_name);
        $this->setOutdatedDb($site_name, $titles);

        /* Gumtree */
        $site_name = 'gumtree';
        $titles    = $this->getOutdatedOffers($site_name);
        $this->setOutdatedDb($site_name, $titles);

        /* Lento */
        $site_name = 'lento';
        $titles    = $this->getOutdatedOffers($site_name);
        $this->setOutdatedDb($site_name, $titles);

        /* Gazeta Praca - Goldenline */
        $site_name = 'gazetapraca';
        $titles    = $this->getOutdatedOffers($site_name);
        $this->setOutdatedDb($site_name, $titles);

        /* Info Praca */
        $site_name = 'infopraca';
        $titles    = $this->getOutdatedOffers($site_name);
        $this->setOutdatedDb($site_name, $titles);

        /* Pracuj.pl */
        $site_name = 'pracujpl';
        $titles    = $this->getOutdatedOffers($site_name);
        $this->setOutdatedDb($site_name, $titles);

        $this->saveLogOutro($this->_logF);

        echo "Script runned successfully!";
    }

    /**
     * @param $site
     * @return mixed
     */
    public function getOutdatedOffers($site)
    {
        switch ($site) {
            case 'olx':
                return $this->getOutdatedOlx();
                break;
            case 'gumtree':
                return $this->getOutdatedGumtree();
                break;
            case 'lento':
                return $this->getOutdatedLento();
                break;
            case 'gazetapraca':
                return $this->getOutdatedGazetapraca();
                break;
            case 'infopraca':
                return $this->getOutdatedInfopraca();
                break;
            case 'pracujpl':
                return $this->getOutdatedPracujpl();
                break;
        }
    }

    /**
     * Gets outdated offers names after successful logging to the website
     */
    public function getOutdatedOlx()
    {
        $logged = $this->logintoOlx();

        if ($logged["status"] == 'success') {
            $archiveHtml = $this->sendPost('http://olx.pl/mojolx/archive/', [], []);

            preg_match_all('~<h3 class="normal brkword fbold" title="(.*?)">~', $archiveHtml, $titles);

            return $titles[1];

        } else {
            return ['status' => $logged["status"], "message" => $logged["message"]];
        }
    }

    /**
     * Gets outdated offers after successful logging to the website
     */
    public function getOutdatedGumtree()
    {
        return ["status" => "error", "message" => "Can't read outdated."];
    }

    /**
     * Gets outdated offers after successful logging to the website
     */
    public function getOutdatedLento()
    {
        return ["status" => "error", "message" => "Can't read outdated."];
    }

    /**
     * Gets outdated offers after successful logging to the website
     */
    public function getOutdatedGazetapraca()
    {
        return ["status" => "error", "message" => "Can't read outdated."];
    }

    /**
     * Gets outdated offers after successful logging to the website
     */
    public function getOutdatedInfopraca()
    {
        return ["status" => "error", "message" => "Can't read outdated."];
    }

    /**
     * Gets outdated offers after successful logging to the website
     */
    public function getOutdatedPracujpl()
    {
        return ["status" => "error", "message" => "Can't read outdated."];
    }

    /**
     * @param $site
     * @param $titles array
     * @return mixed
     */
    public function setOutdatedDb($site, $titles)
    {
        switch ($site) {
            case 'olx':
                return $this->setOutdated("olx", $titles);
                break;
            case 'gumtree':
                return $this->setOutdated("gumtree", $titles);
                break;
            case 'lento':
                return $this->setOutdated("lento", $titles);
                break;
            case 'gazetapraca':
                return $this->setOutdated("gazetapraca", $titles);
                break;
            case 'infopraca':
                return $this->setOutdated("infopraca", $titles);
                break;
            case 'pracujpl':
                return $this->setOutdated("pracujpl", $titles);
                break;
        }
    }

    /**
     * @param $site
     * @param $titles
     * @return bool
     */
    public function setOutdated($site, $titles)
    {
        $query = '';
        //TODO: Zmiany!
        foreach ($titles as $offer_title) {
            $query .= "UPDATE portal_post rp ";
            $query .= "JOIN announcement rc ON rc.id = rp.announcement_id ";
            $query .= "SET rp.status = 2 ";
            $query .= "WHERE rc.name LIKE '" . $offer_title . "' AND portal_id LIKE '" . $site . "'; ";
        }

        $db      = Yii::$app->db;
        $command = $db->createCommand($query);

        return $command->execute() == 0 ? true : false;
    }

    /**
     * Check outdated
     */
    public static function updateOutdatedCron()
    {
        $post = new Post();
        $post->checkOutdated();
    }

    /**
     * Gathers all posting info and generates callback message
     * @param $name
     * @param $status_posting
     * @param $status_before
     * @param null $message
     * @param null $code
     * @param null $link
     * @return array
     */
    public function renderPostingMessage($name, $status_posting, $status_before, $message = null, $code = null, $link = null)
    {
        $msg          = $message;
        $status_after = null;

        /** If wasn't posted - set to post **/
        if ((isset($status_before) && $status_before == "success" && $status_posting == 0)) {
            $status_after = PortalPost::STATUS_POSTED;
        } else if ((int)$status_posting == PortalPost::STATUS_POSTED || (int)$status_posting == PortalPost::STATUS_SENT) {
            $status_after = $status_posting;
        } else {
            $status_after = PortalPost::STATUS_NOTPOSTED;
        }

        /** Status that was before posting */
        switch ((int)$status_posting) {
            case PortalPost::STATUS_SENT:
                $msg .= $name . " - " . Yii::t('app', 'Proces publikacji jest już w trakcie') . ". ";
                break;
            case PortalPost::STATUS_POSTED:
                $msg .= $name . " - " . Yii::t('app', 'Jest już opublikowana') . ". ";
                break;
            case 0:
                $msg .= $name . " - " . Yii::t('app', 'Nie była wcześniej opublikowana') . ". ";
                break;
        }

        $status_code = null;
        switch ($code) {
            default:
            case null:
            case 'posted':
                $status_code = $status_after;
                break;
            case 'activate':
                $status_code = PortalPost::STATUS_SENT;
                break;
        }

        return ['status_after' => $status_after, 'code' => $status_code, 'msg' => $msg, 'link' => $link];
    }

    /**
     * Renders description with or without options depending on template or announcement
     * If html isn't allowed - strip tags, if it is - render with tags
     *
     * @param integer $portal_id
     * @param $announcement Announcement2
     * @param $opt
     * @param bool $allow_html
     * @return string
     * @internal param $desc
     */
    private function renderDescription($portal_id, $announcement, $opt, $allow_html = false)
    {
        if ($portal_id == Portal::INTERIMAX_ID) {
            $layout = AnnouncementBuilder::LAYOUT_INTERIMAX;
        } else if ($portal_id == Portal::ATEAM_ID) {
            $layout = AnnouncementBuilder::LAYOUT_ATEAM;
        } else {
            $layout = AnnouncementBuilder::LAYOUT_DEFAULT;
        }

        if ($allow_html) {
            return $announcement->getHtmlContent($layout);
        } else {
            return $announcement->getRawContent($layout);
        }
//        return isset($template)
//            ? ($allow_html == false
//                ? strip_tags($this->renderTemplateDescription($template, $opt, $allow_html))
//                : $this->renderTemplateDescription($template, $opt, $allow_html))
//            : ($allow_html == false
//                ? strip_tags($this->renderAnnouncementDescription($data, $opt, $allow_html))
//                : $this->renderAnnouncementDescription($data, $opt, $allow_html));
    }

    /**
     * Renders description from announcement data
     *
     * @param $data Announcement2
     * @param $opt
     * @param bool $allow_html
     * @return string
     * @internal param $site_id
     * @internal param $desc
     */
    private function renderAnnouncementDescription($data, $opt, $allow_html = false)
    {
        if ($allow_html) {
            $cb = '<center>';
            $ce = '</center><br/>';
        } else {
            $cb = '';
            $ce = '';
        }

        $br = "\n";

        switch ($data["salary_currency"]) {
            case 1:
                $curr = PersonInterface::CURRENCY_PL . ' ';
                break;
            case 2:
                $curr = PersonInterface::CURRENCY_EUR . ' ';
                break;
            case 3:
                $curr = PersonInterface::CURRENCY_USD . ' ';
                break;
            default:
                $curr = ' ';
                break;
        }

        $name            = $allow_html ? $data["name"] : strip_tags($data["name"]);
        $description     = $allow_html ? $data["description"] : strip_tags($data["description"]);
        $requirements    = $allow_html ? $data["requirements"] : strip_tags($data["requirements"]);
        $profession_id   = $data["profession_id"];
        $salary_type     = $data["salary_type"];
        $salary_period   = $data["salary_period"];
        $city            = $data["city"];
        $country         = Country::findOne($data["country_id"])->name;//$data["country_id"];
        $date_start      = $data["date_start"];
        $date_end        = $data["date_end"];
        $work_type_id    = $data["work_type"];
        $work_date_start = $data["work_date_start"];
        $work_date_end   = $data["work_date_end"];
        //$vacancies          = $data["vacancies"]; //ONLY IN RECRUITMENT
        $experience_id      = $data["experience_id"];
        $education_title_id = $data["education_title_id"];
        $contract_id        = $data["contract_id"];
        $state_id           = $data["state_id"];
        $state              = isset($state_id) ? State::findOne($state_id)->name : null;
        $salary_full        = '';
        $apply_link_full    = '';

        $salary_type_txt = '';
        if (isset($salary_type))
            switch ($salary_type) {
                case Job::SALARY_TYPE_NETTO:
                    $salary_type_txt = Yii::t('app', 'netto{spc}', ['spc' => ' ']);
                    break;
                case Job::SALARY_TYPE_BRUTTO:
                    $salary_type_txt = Yii::t('app', 'brutto{spc}', ['spc' => ' ']);
                    break;
            }

        $salary_per_txt = '';
        if (isset($salary_period))
            switch ($salary_period) {
                case Job::SALARY_PERIOD_DAILY:
                    $salary_per_txt = Yii::t('app', 'dziennie{spc}', ['spc' => ' ']);
                    break;
                case Job::SALARY_PERIOD_WEEKLY:
                    $salary_per_txt = Yii::t('app', 'tygodniowo{spc}', ['spc' => ' ']);
                    break;
                case Job::SALARY_PERIOD_MONTHLY:
                    $salary_per_txt = Yii::t('app', 'miesięcznie{spc}', ['spc' => ' ']);
                    break;
                case Job::SALARY_PERIOD_QUARTERLY:
                    $salary_per_txt = Yii::t('app', 'kwartalnie{spc}', ['spc' => ' ']);
                    break;
                case Job::SALARY_PERIOD_ANNUAL:
                    $salary_per_txt = Yii::t('app', 'rocznie{spc}', ['spc' => ' ']);
                    break;
            }

        $btn_lbl = Yii::t('app', 'Aplikuj tutaj') . ': ';

        //TODO: Change
        //$btn_link = Url::to($data["main_link"] . '&sid=' . $site_id, true);
        //http://cvx2.interimax.net.pl/external/interimax/oferta/oferta-pracy-starszy-administrator-baz-danych/3c1fe046#Aplikuj . '&sid=' . $site_id, true);
        $btn_link = Settings::getPublicRoute('interimax/oferta/') . Inflector::slug($data["name"]) . '/' . $data["hash"];
        $btn      = $allow_html
            ? '<button type="button" onclick="javascript:window.location=\'' . $btn_link . '\'">' . $btn_lbl . '</button>'
            : $btn_lbl . $btn_link;

        $desc = '';
        //Nazwa
        isset($name) ? $desc .= $cb . $name . $ce . $br : '';
        //Miejsce
        isset($city) || isset($country) ? $desc .= $cb . Yii::t('app', 'Miejsce pracy: ') . $city . ' ' . $country . ' ' . $state . $ce . $br : ''; //Yii::t('app', '')
        //Zawód
        isset($profession_id) ? $desc .= $cb . Yii::t('app', 'Stanowisko: ') . Profession::findOne($profession_id)->name . $ce . $br : '';
        //Daty rekrutacji
        isset($date_start) && isset($date_end)
            ? $desc .= $cb . Yii::t('app', 'Czas trwania rekrutacji: ') . $date_start . ' - ' . $date_end . $ce . $br
            : '';

        //Wypłata i link
        if (!empty($opt)) {
            $desc .= $br . $br;
            isset($opt["ss"]) && $opt["ss"] == true
                ? $salary_full = Yii::t('app', 'Wynagrodzenie{spc}', ['spc' => ': ']) . $data["salary"] . $curr . $salary_type_txt . $salary_per_txt . "\n"
                : $salary_full = '';
            isset($opt["sl"]) && $opt["sl"] == true
                ? $apply_link_full = $btn . "\n"
                : $apply_link_full = '';
        }

        //Typ pracy
        if (isset($work_type_id)) {
            switch ($work_type_id) {
                case Job::WORK_TYPE_TEMPORARY:
                    $desc .= $cb . Yii::t('app', 'Praca tymczasowa') . $ce . $br;
                    break;
                case Job::WORK_TYPE_FULL_TIME:
                    $desc .= $cb . Yii::t('app', 'Praca na pełny etat') . $ce . $br;
                    break;
            }
        }

        //Pozostałe
        isset($contract_id) ? $desc .= '' : '';
        isset($work_date_start) && isset($work_date_end)
            ? $desc .= $cb . Yii::t('app', 'Czas trwania pracy: ') . $work_date_start . ' - ' . $work_date_start . $ce . $br
            : '';
        //isset($vacancies) ? $desc .= $cb . Yii::t('app', 'Wakaty: ') . $vacancies . $ce . $br : ''; //ONLY IN RECRUITMENT
        isset($experience_id) ? $desc .= $cb . Experience::findOne($experience_id)->name . $ce . $br : '';
        isset($education_title_id) ? $desc .= $cb . EducationTitle::findOne($education_title_id)->name . $ce . $br : '';

        $desc .= $br;
        isset($description) ? $desc .= Yii::t('app', 'Opis:') . $br . $br . $description . $br . $br : '';
        isset($requirements) ? $desc .= Yii::t('app', 'Wymagania:') . $br . $br . $requirements . $br . $br : '';

        $desc .= $salary_full;
        $desc .= $apply_link_full;

        //TODO: póki co na sztywno
        $desc .= $br;
        $end = 'W CV należy zamieścić klauzulę: ' . $br;
        $end .= '"Wyrażam zgodę na przetwarzanie moich danych osobowych zawartych w mojej ofercie pracy dla ';
        $end .= 'potrzeb niezbędnych do realizacji procesu rekrutacji (zgodnie z Ustawą z dnia 29.08.1997 roku o ';
        $end .= 'Ochronie Danych Osobowych; tekst jednolity: Dz. U. z 2002r. Nr 101, poz. 926 ze zm.).".';

        $desc .= isset($end) ? Yii::t('app', $end) : '';

        return $desc;
    }

    /**
     * Renders description from template
     *
     * @param $template Publication
     * @param $opt
     * @param $allow_html
     * @return string
     */
    private function renderTemplateDescription($template, $opt, $allow_html)
    {
        if ($allow_html) {
            $br = '<br/>';
            $cb = '<center>';
            $ce = '</center>' . $br;
        } else {
            $br = "\n";
            $cb = '';
            $ce = '';
        }


        $desc = '';
        $desc .= $this->generateField($template, 'title', $opt, $br . $br, $allow_html);
        $desc .= $this->generateField($template, 'company_description', $opt, $br . $br, $allow_html);
        $desc .= $this->generateField($template, 'profession_description', $opt, $br . $br, $allow_html);
        $desc .= $this->generateField($template, 'other_description', $opt, $br . $br, $allow_html);
        $desc .= $this->generateField($template, 'requirements_description', $opt, $br . $br, $allow_html);
        $desc .= $this->generateField($template, 'we_offer', $opt, $br, $allow_html);
        $desc .= $this->generateField($template, 'required_documents', $opt, $br, $allow_html);
        $desc .= $this->generateField($template, 'languages', $opt, $br, $allow_html);
        $desc .= $this->generateField($template, 'driving_licences', $opt, $br, $allow_html);
        $desc .= $this->generateField($template, 'workPeriodPretty', $opt, $br, $allow_html);
        $desc .= $this->generateField($template, 'workTypePretty', $opt, $br, $allow_html);
        $desc .= $this->generateField($template, 'salaryPretty', $opt, $br, $allow_html);
        $desc .= $this->generateField($template, 'contract', $opt, $br, $allow_html);
        $desc .= $this->generateField($template, 'city', $opt, $br . $br, $allow_html);
        //TODO:: no country

        if ($template->template_type !== Publication::TYPE_INTERNAL AND $template->template_type !== Publication::TYPE_A_TEAM) {
            //TODO: Change
            //$desc .= $this->generateField($template, 'apply_link', $opt, $br, $allow_html);
            //$btn_link = Url::to($data["main_link"] . '&sid=' . $site_id, true);
            //http://cvx2.interimax.net.pl/external/interimax/oferta/oferta-pracy-starszy-administrator-baz-danych/3c1fe046#Aplikuj . '&sid=' . $site_id, true);

            $hash    = Announcement::findOne($template->announcement->id)->hash;
            $btn_lbl = Yii::t('app', 'Aplikuj tutaj:');

            $btn_link = Settings::getPublicRoute(['interimax/oferta']) . '/' . Inflector::slug($template->name) . '/' . $hash;
            $btn      = $allow_html
                ? '<button type="button" onclick="javascript:window.location=\'' . $btn_link . '\'">' . $btn_lbl . '</button>'
                : $btn_lbl . $btn_link;

            $desc .= $btn . $br;
        }

        $desc .= $this->generateField($template, 'clause', $opt, $br . $br, $allow_html);

        return $desc;
    }

    /**
     * Depends on portal returns template that needs to be used
     *
     * @param $site_id
     * @return int
     */
    private function getPublicationType($site_id)
    {
        switch ($site_id) {
            case Portal::OLX_ID:
            case Portal::GUMTREE_ID:
            case Portal::LENTO_ID:
            case Portal::GAZETAPRACA_ID:
            case Portal::GOLDENLINE_ID:
            case Portal::INFOPRACA_ID:
            case Portal::PRACUJPL_ID:
                return Publication::TYPE_POLISH;
                break;
            case Portal::ATEAM_ID:
                return Publication::TYPE_A_TEAM;
                break;
            case Portal::INTERIMAX_ID:
                return Publication::TYPE_INTERNAL;
                break;
            default:
                return Publication::TYPE_INTERNATIONAL;
                break;
        }
    }

    /**
     * Does it have template for site?
     *
     * @param $template_type
     * @param $id
     * @return array|null|\yii\db\ActiveRecord
     */
    public function hasTemplate($template_type, $id)
    {

        $announcement = Announcement::findOne($id);
        $template     = $announcement->getAnnouncementTemplateByType($template_type);

        return isset($template) ? $template : null;
    }

    /**
     * Generate one field
     *
     * @param $template - template data
     * @param $attribute - field
     * @param $options - should it show link and salary
     * @param $br - enter depends on allo_html
     * @param bool $allow_html
     * @return string
     */
    private function generateField($template, $attribute, $options, $br, $allow_html = false)
    {
        $model = new Publication();

        switch ($attribute) {
//            case 'title':
            case 'company_description':
            case 'profession_description':
            case 'other_description':
            case 'requirements_description':
            case 'we_offer':
            case 'required_documents':
            case 'languages':
            case 'driving_licences':
            case 'workPeriodPretty':
                if (isset($template->{$attribute})) {
                    $label = $model->getAttributeLabel($attribute);
                    $value = $template->{$attribute} . $br;
                } else {
                    $label = $value = '';
                }
                break;
            case 'workTypePretty':
                if (isset($template->workTypePretty)) {
                    $label = $model->getAttributeLabel('workTypePretty');
                    $value = $template->workTypePretty . $br;
                } else {
                    $label = $value = '';
                }
                break;
            case 'salaryPretty':
                if (isset($template->salaryPretty) AND $options["ss"] == true) {
                    $label = $model->getAttributeLabel('salaryPretty');
                    $value = $template->salaryPretty . $br;
                } else {
                    $label = $value = '';
                }
                break;
            case 'contract':
                if (isset($template->contract->name)) {
                    $label = $model->getAttributeLabel('contract');
                    $value = $template->contract->name . $br;
                } else {
                    $label = $value = '';
                }
                break;
            case 'city':
                if (isset($template->city)) {
                    $label = $model->getAttributeLabel('city');
                    $value = $template->city . $br;
                } else {
                    $label = $value = '';
                }
                break;
//            case 'apply_link':
//                return $options["sl"] == true && isset($template->apply_link) ? $model->getAttributeLabel('apply_link') . ': ' . $template->apply_link . $br : '';
//                break;
            case 'clause':
                if (isset($template->clause)) {
                    $label = $model->getAttributeLabel('clause');
                    $value = $template->clause . $br;
                } else {
                    $label = $value = '';
                }
                break;
        }

        if ($allow_html) $label = '<strong>' . $label . '</strong>';

        $field = (!empty($label) AND !empty($value)) ? ($label . ': ' . $br . $value) : '';

        return $field;
    }

    /**
     * Converts category from DB to Pracuj.pl category
     * @param $ref_id
     * @return array
     */
    private function getPracujCategory($ref_id)
    {

        /**
         * #5001 - ctl00$DefaultContent$lstNewCategories$0 - Administracja biurowa (id 5001. name 0)
         * #5001#5001001 - ctl00$DefaultContent$lstNewSubCategories$0 - Administracja: Sekretariat / Recepcja (id 5001001. name 0)
         *
         * Nazwa kategorii np #5001-0#5001001-0
         */

        $cat_prefix    = 'ctl00$DefaultContent$lstNewCategories$';
        $subcat_prefix = 'ctl00$DefaultContent$lstNewSubCategories$';

        $ids = explode("#", $ref_id);
        $cat = $ids[0];
        $sub = $ids[1];

        $catData = explode("-", $cat);
        $subData = explode("-", $sub);

        $catValue = $catData[0];
        $catInput = $cat_prefix . $catData[1];

        $subValue = '#' . $subData[0];
        $subInput = $subcat_prefix . $subData[1];

        return [
            'cat_value'    => $catValue,
            'cat_input'    => $catInput,
            'subcat_value' => $subValue,
            'subcat_input' => $subInput,
        ];
    }

    /**
     * @param $postOfferUrl
     * @return mixed
     */
    private function runStepInitPracujPl($postOfferUrl)
    {
        //$postOfferurl = 'https://sklep.pracuj.pl/Offers/OfferSelect.aspx';
        //$postOfferurl = 'https://sklep.prblacuj.pl/Offers/OfferSteps.aspx?packageId=427&setFrstStep=True&cs=EC1A0D87C45856A90679DDFE5ACDB78E';
        $pickTypeUrl = 'https://sklep.pracuj.pl/Offers/OfferSelect.aspx';

        $output = $this->sendPost($postOfferUrl, [], [
            'Host:sklep.pracuj.pl',
            'Referer: ' . $postOfferUrl,
            'User-Agent:Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
        ]);

        $pattern2 = '~<h2>Object moved to <a href="(.*?)">here</a>.</h2>~';

        preg_match_all(
            $pattern2,
            (string)$output,
            $feedback
        );

        $t         = explode('name="__VIEWSTATE" id="__VIEWSTATE" value="', $output);
        $t         = explode('" />', $t[1]);
        $viewstate = $t[0];

        $t               = explode('name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="', $output);
        $t               = explode('" />', $t[1]);
        $eventvalidation = $t[0];

        $t        = explode('name="ctl00$hfEmail" id="ctl00_hfEmail" value="', $output);
        $t        = explode('" />', $t[1]);
        $ct_email = $t[0];

        $output2 = $this->sendPost($pickTypeUrl, [
            '__EVENTARGUMENT'                                                          => '',
            '__EVENTTARGET'                                                            => '',
            '__EVENTVALIDATION'                                                        => $eventvalidation,
            '__VIEWSTATE'                                                              => $viewstate,
            'ctl00$DefaultContent$rptStandardAd$ctl00$productBox$imgBtnOrderNoCredits' => 'Zamów teraz',
            'ctl00$ctrlWanderBasketPopUp$hdnHasBeenChanged'                            => '',
            'ctl00$hfEmail'                                                            => $ct_email,
        ], [
            'Host: sklep.pracuj.pl',
            'Referer: https://sklep.pracuj.pl/Offers/OfferSelect.aspx',
            'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
        ]);

        $pattern3 = '~<h2>Object moved to <a href="(.*?)">here</a>.</h2>~';

        preg_match_all(
            $pattern3,
            (string)$output2,
            $feedback
        );

        $redirected = $feedback[1][0];

        $firstStepUrl = 'https://sklep.pracuj.pl' . $redirected;
        //$firstStepUrl = 'https://sklep.pracuj.pl//Offers/OfferSteps.aspx?packageId=427&amp;setFrstStep=True&amp;cs=EC1A0D87C45856A90679DDFE5ACDB78E';
        //http://sklep.pracuj.pl/Offers/OfferSteps.aspx?packageId=427&setFrstStep=True&cs=EC1A0D87C45856A90679DDFE5ACDB78E

        $check = $this->sendPost($firstStepUrl, [], [
            'Host:sklep.pracuj.pl',
            'Referer: http://sklep.pracuj.pl/Offers/OfferSelect.aspx',
            'User-Agent:Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
        ]);

        $pattern4 = '~<h2>Object moved to <a href="(.*?)">here</a>.</h2>~';

        preg_match_all(
            $pattern4,
            (string)$check,
            $feedback
        );

        $redirected = $feedback[1][0];

        $NextStepUrl = 'https://sklep.pracuj.pl' . $redirected;

        $next = $this->sendPost($NextStepUrl, [], [
            'Host:sklep.pracuj.pl',
            'Referer: ' . $firstStepUrl,
            'User-Agent:Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
        ]);

        $t         = explode('name="__VIEWSTATE" id="__VIEWSTATE" value="', $output);
        $t         = explode('" />', $t[1]);
        $viewstate = $t[0];

        $t               = explode('name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="', $output);
        $t               = explode('" />', $t[1]);
        $eventvalidation = $t[0];

        $t        = explode('name="ctl00$hfEmail" id="ctl00_hfEmail" value="', $output);
        $t        = explode('" />', $t[1]);
        $ct_email = $t[0];


        $output3 = $this->sendPost($firstStepUrl, [
            '__EVENTVALIDATION'            => $eventvalidation,
            '__VIEWSTATE'                  => $viewstate,
            'ctl00$DefaultContent$btnNext' => 'Przejdź do tworzenia oferty pracy',
            'ctl00$hfEmail'                => $ct_email,
        ], [
            'Host: sklep.pracuj.pl',
            'Referer: ' . $pickTypeUrl,
            'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
        ]);

        return $output;
    }

    /**
     * @param $link1
     * @param $offername
     * @param $fullname
     * @return mixed
     */
    private function runStepOnePracujPl($link1, $offername, $fullname)
    {
        $result = $this->sendPost($link1, null);

        $t         = explode('name="__VIEWSTATE" id="__VIEWSTATE" value="', $result);
        $t         = explode('" />', $t[1]);
        $viewstate = $t[0];

        $t        = explode('name="ctl00$hfEmail" id="ctl00_hfEmail" value="', $result);
        $t        = explode('" />', $t[1]);
        $ct_email = $t[0];

        $output = $this->sendPost($link1, [
            '__EVENTTARGET'                                        => '',
            '__EVENTVALIDATION'                                    => '',
            '__LASTFOCUS'                                          => '',
            '__SCROLLPOSITIONX'                                    => '0',
            '__SCROLLPOSITIONY'                                    => '228',
            '__VIEWSTATE'                                          => $viewstate,
            'ctl00$DefaultContent$hdCompanyName'                   => '',
            'ctl00$DefaultContent$hdIsRelocationSet'               => '',
            'ctl00$DefaultContent$hdForbiddenContry'               => 'polska',
            'ctl00$DefaultContent$hdViewMode'                      => '1',
            'ctl00$DefaultContent$hdSelectedRegionId'              => '',
            'ctl00$DefaultContent$tbJobTitle'                      => $offername,
            'ctl00$DefaultContent$tbCompanyName'                   => $fullname,
            'ctl00_DefaultContent_tbxCountry'                      => 'Polska',
            'ctl00$DefaultContent$tbxCity'                         => 'Kraków, małopolskie',
            'ctl00$DefaultContent$tbxCountryCity'                  => '',
            'ctl00$DefaultContent$tbxCityAddress'                  => '',
            'ctl00$DefaultContent$tbxCountryAddress'               => '',
            'ctl00$DefaultContent$hfCityCoordinates'               => '',
            'ctl00$DefaultContent$btnAddCity'                      => '',
            'ctl00$DefaultContent$btnAddCountry'                   => '',
            'ctl00$DefaultContent$ddlRegionLocations$0'            => '',
            'ctl00$DefaultContent$ddlRegionLocations$1'            => '',
            'ctl00$DefaultContent$ddlRegionLocations$2'            => '',
            'ctl00$DefaultContent$ddlRegionLocations$3'            => '',
            'ctl00$DefaultContent$ddlRegionLocations$4'            => '',
            'ctl00$DefaultContent$ddlRegionLocations$5'            => '6',
            'ctl00$DefaultContent$ddlRegionLocations$6'            => '',
            'ctl00$DefaultContent$ddlRegionLocations$7'            => '',
            'ctl00$DefaultContent$cbxRegionPlus'                   => '',
            'ctl00$DefaultContent$hfCountryCoordinates'            => '',
            'ctl00$DefaultContent$hfCityRegionId'                  => '',
            'ctl00$DefaultContent$hfHasData'                       => '0',
            'ctl00$DefaultContent$HShowHiddenOfferAddonCreditInfo' => 'false',
            'ctl00$DefaultContent$HHiddenOfferPrice'               => ' +149  zł netto',
            'ctl00$DefaultContent$HCreditsForHiddenOfferAddon'     => '',
            'ctl00$DefaultContent$btnBack'                         => 'Wróć',
            'ctl00$DefaultContent$btnNext'                         => 'Dalej',
            'ctl00$DefaultContent$btnBackToOfferList'              => '',
            'ctl00$DefaultContent$btnPublishCorrectedOffer'        => '',
            'ctl00$DefaultContent$btnProceedAddLocation'           => '',
            'ctl00$hfEmail'                                        => $ct_email,
            'ctl00$DefaultContent$hfLocation'                      => '282|6',//kraków małopolskie

        ], [
            'Host:sklep.pracuj.pl',
            'Referer: https://sklep.pracuj.pl/Offers/OfferSteps.aspx?packageId=427&setFrstStep=True&cs=EC1A0D87C45856A90679DDFE5ACDB78E',
            'User-Agent:Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
        ]);

        return $output;
    }

    /**
     * Enter way to apply
     * @param $link2
     * @param $offername
     * @param $offerlink
     * @param $offeremail
     * @param $ref
     */
    private function runStepTwoPracujPl($link2, $offername, $offerlink, $offeremail, $ref)
    {
        $result = $this->sendPost($link2, null);

        $t         = explode('name="__VIEWSTATE" id="__VIEWSTATE" value="', $result);
        $t         = explode('" />', $t[1]);
        $viewstate = $t[0];

        $t               = explode('name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="', $result);
        $t               = explode('" />', $t[1]);
        $eventvalidation = $t[0];

        $t        = explode('name="ctl00$hfEmail" id="ctl00_hfEmail" value="', $result);
        $t        = explode('" />', $t[1]);
        $ct_email = $t[0];

        if (!empty($offerlink)) {
            $additional = [
                'ctl00$DefaultContent$rptContacts$ctl01$tbWebPage' => $offerlink,
            ];
        } else {
            $additional = [
                'ctl00$DefaultContent$rptContacts$ctl01$tbEMail' => $offeremail,
            ];
        }

        $output = $this->sendPost($link2, array_merge([
            'ctl00$DefaultContent$rptContacts$ctl01$tbRefNumber'        => $ref,
            '__VIEWSTATE'                                               => $viewstate,
            '__EVENTTARGET'                                             => '',
            '__EVENTARGUMENT'                                           => '',
            '__SCROLLPOSITIONX'                                         => '0',
            '__SCROLLPOSITIONY'                                         => '0',
            '__EVENTVALIDATION'                                         => $eventvalidation,
            'ctl00$hfEmail'                                             => $ct_email,
            'ctl00$btnBackToPanel'                                      => 'Przerwij i dokończ później',
            'ctl00$DefaultContent$hdnJobTitle'                          => $offername,
            'ctl00$DefaultContent$btnBack'                              => 'Wróć',
            'ctl00$DefaultContent$btnNext'                              => 'Dalej',
            'ctl00$DefaultContent$btnBackToOfferList'                   => '',
            'ctl00$DefaultContent$btnPublishCorrectedOffer'             => '',
            'ctl00_DefaultContent_ddlApplicationsType_0'                => '4',
            'ctl00_DefaultContent_ddlApplicationsType_1'                => '2',
            'ctl00_DefaultContent_ddlApplicationsType_2'                => '5',
            'ctl00$DefaultContent$rptContacts$ctl01$hdnOfferIdContacts' => '4709376',

        ], $additional), [
            'Host:sklep.pracuj.pl',
            'Referer: https://sklep.pracuj.pl/Offers/OfferSteps.aspx?packageId=427&setFrstStep=True&cs=EC1A0D87C45856A90679DDFE5ACDB78E',
            'User-Agent:Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
        ]);
    }

    /**
     * @param $link3
     * @param $description
     * @param $requirements
     */
    private function runStepThreePracujPl($link3, $description, $requirements)
    {
        $result = $this->sendPost($link3, null);

        $t         = explode('name="__VIEWSTATE" id="__VIEWSTATE" value="', $result);
        $t         = explode('" />', $t[1]);
        $viewstate = $t[0];

        $t            = explode('name="__PREVIOUSPAGE" id="__PREVIOUSPAGE" value="', $result);
        $t            = explode('" />', $t[1]);
        $previouspage = $t[0];

        $t               = explode('name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="', $result);
        $t               = explode('" />', $t[1]);
        $eventvalidation = $t[0];

        $t        = explode('name="ctl00$hfEmail" id="ctl00_hfEmail" value="', $link3);
        $t        = explode('" />', $t[1]);
        $ct_email = $t[0];

        $output = $this->sendPost($link3, [
            '__EVENTTARGET'                                 => '',
            '__EVENTARGUMENT'                               => '',
            '__VIEWSTATE'                                   => $viewstate,
            '__PREVIOUSPAGE'                                => $previouspage,
            '__EVENTVALIDATION'                             => $eventvalidation,
            'ctl00$hfEmail'                                 => $ct_email,
            'ctl00$DefaultContent$rblLanguages'             => '1', //1 - Polski języj oferty, 2 - Ang
            'ctl00$DefaultContent$tbCompanyDescription'     => '',
            'ctl00$DefaultContent$tbJobDescription'         => $description,
            'ctl00$DefaultContent$tbRequirements'           => $requirements,
            'ctl00$DefaultContent$tbOpportunities'          => '',
            'ctl00$DefaultContent$hdnContact'               => '&lt;center> Osoby zainteresowane prosimy o przesyłanie aplikacji klikając w przycisk aplikowania.&lt;/center> ',
            'ctl00$DefaultContent$hdnContactEng'            => '&lt;center>If you are interested, please send your CV via Aplikuj button below.&lt;/center>',
            'ctl00$DefaultContent$tbContact'                => '&lt;p&gt;Osoby zainteresowane prosimy o przesyłanie aplikacji klikając w przycisk aplikowania.&lt;/p&gt;',
            'ctl00$DefaultContent$hdnClause'                => 'Prosimy o zawarcie w CV klauzuli: „Wyrażam zgodę na przetwarzanie danych osobowych zawartych w mojej ofercie pracy dla potrzeb niezbędnych do realizacji procesu rekrutacji prowadzonego przez __________ z siedzibą w ____________ zgodnie z ustawą z dnia 29 sierpnia 1997 r. o ochronie danych osobowych (tj. Dz. U. z 2014 r. poz. 1182, 1662)”.&lt;br />&lt;br />Informujemy, że Administratorem danych jest  ____________  z siedzibą w __________________ przy ul. _______________. Dane zbierane są dla potrzeb rekrutacji. Ma Pani/Pan prawo dostępu do treści swoich danych oraz ich poprawiania. Podanie danych w zakresie określonym przepisami ustawy z dnia 26 czerwca 1974 r. Kodeks pracy oraz aktów wykonawczych jest obowiązkowe. Podanie dodatkowych danych osobowych jest dobrowolne.',
            'ctl00$DefaultContent$hdnClauseEng'             => 'Please include the following statement  in your application: &quot;I hereby authorize you to process my personal data included in my job application for the needs of the recruitment process in accordance with the Personal Data Protection Act dated 29.08.1997 (uniform text: Journal of Laws of the Republic of Poland 2014, item 1182, 1662)&quot;',
            'ctl00$DefaultContent$tbClause'                 => '',
            'ctl00$DefaultContent$btnBack'                  => 'Wróć',
            'ctl00$DefaultContent$btnNext'                  => 'Dalej',
            'ctl00$DefaultContent$btnBackToOfferList'       => '',
            'ctl00$DefaultContent$btnPublishCorrectedOffer' => '',
            'ctl00$DefaultContent$hdnJobTitle'              => '',


        ], [
            'Host:sklep.pracuj.pl',
            'Referer: https://sklep.pracuj.pl/Offers/OfferSteps.aspx?packageId=427&setFrstStep=True&cs=EC1A0D87C45856A90679DDFE5ACDB78E',
            'User-Agent:Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
        ]);
    }

    /**
     * @param $link4
     * @param $jobCatId
     * @param $jobSCatId
     * @param $jobSCatInput
     * @param $jobCatInput
     */
    private function runStepFourPracujPl($link4, $jobCatId, $jobSCatId, $jobSCatInput, $jobCatInput)
    {
        $result = $this->sendPost($link4, null);

        $t               = explode('name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="', $result);
        $t               = explode('" />', $t[1]);
        $eventvalidation = $t[0];

        $t        = explode('name="ctl00$hfEmail" id="ctl00_hfEmail" value="', $result);
        $t        = explode('" />', $t[1]);
        $ct_email = $t[0];

        $output = $this->sendPost($link4, [
            '__EVENTTARGET'                                             => '',
            '__EVENTARGUMENT'                                           => '',
            '__EVENTVALIDATION'                                         => $eventvalidation,
            'ctl00$hfEmail'                                             => $ct_email,
            'ctl00$DefaultContent$btnBack'                              => 'Wróć',
            'ctl00$DefaultContent$btnNext'                              => 'Dalej',
            'ctl00$DefaultContent$ddlEmploymentForm'                    => '',
            //1 - Pełen etat, 2 - część, 3 - czasowa, 4 - kontrakt
            'ctl00$DefaultContent$ddlSalary'                            => '0', //dowolne
            'ctl00$DefaultContent$ddlEducation'                         => '',
            //1 - wyższe, 2 - srednie, 3 - zawodowe, 4 - w trakcje, 5 - podstawowe
            'ctl00$DefaultContent$ddlExperience'                        => '',
            //1 - brak, 2 - 0-2 lata, 3 - 2-4lata, 4 - poyzej 4
            'ctl00$DefaultContent$ddlPositionLevels'                    => '4', //specjalista
            'ctl00$DefaultContent$hdnJobTitle'                          => '',
            'ctl00$DefaultContent$hdnNewCategoryMax'                    => '1',
            'ctl00$DefaultContent$hdnNewCategoriesMin'                  => '0',
            'ctl00$DefaultContent$hdnNewSubCategoriesMax'               => '2',
            'ctl00$DefaultContent$hdnNewSubCategoriesMin'               => 1,
            'ctl00$DefaultContent$lstBranches$' . $jobCatInput          => '1000050', //Usługi inne,$jobCatId
            'ctl00$DefaultContent$lstNewSubCategories$' . $jobSCatInput => $jobSCatId,
            //ctl00$DefaultContent$lstNewSubCategories$185 = #5012#5012 for inne
            'ctl00$DefaultContent$tbKeywords'                           => '',
        ], [
            'Host:sklep.pracuj.pl',
            'Referer: https://sklep.pracuj.pl/Offers/OfferSteps.aspx?packageId=427&setFrstStep=True&cs=EC1A0D87C45856A90679DDFE5ACDB78E',
            'User-Agent:Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
        ]);
    }

    /**
     * @param $link5
     */
    private function runStepFivePracujPl($link5)
    {
        $result = $this->sendPost($link5, null);

        $t               = explode('name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="', $result);
        $t               = explode('" />', $t[1]);
        $eventvalidation = $t[0];


        $t         = explode('name="__VIEWSTATE" id="__VIEWSTATE" value="', $result);
        $t         = explode('" />', $t[1]);
        $viewstate = $t[0];

        $t        = explode('name="ctl00$hfEmail" id="ctl00_hfEmail" value="', $result);
        $t        = explode('" />', $t[1]);
        $ct_email = $t[0];

        $output = $this->sendPost($link5, [
            '__EVENTTARGET'                     => '',
            '__EVENTARGUMENT'                   => '',
            '__EVENTVALIDATION'                 => $eventvalidation,
            '__LASTFOCUS'                       => '',
            '__SCROLLPOSITIONX'                 => '0',
            '__SCROLLPOSITIONY'                 => '570',
            '__VIEWSTATE'                       => $viewstate,
            'ctl00$DefaultContent$btnBack'      => 'Wróć',
            'ctl00$DefaultContent$btnNext'      => 'Dalej',
            'ctl00$DefaultContent$tbxStartDate' => date('Y-m-d'),
            'ctl00$hfEmail'                     => $ct_email,
        ], [
            'Host:sklep.pracuj.pl',
            'Referer: https://sklep.pracuj.pl/Offers/OfferSteps.aspx?packageId=427&setFrstStep=True&cs=EC1A0D87C45856A90679DDFE5ACDB78E',
            'User-Agent:Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
        ]);
    }

    /**
     * @param $site_name
     * @param $redirected_to
     * @param $main_cat
     * @return null|string
     */
    private function getAnnouncementLink($site_name, $redirected_to, $main_cat = null, $announcement_title = null)
    {
        switch ($site_name) {
            case 'olx':
                return $this->generateOlxLink($redirected_to, $main_cat, $announcement_title);
                break;
            case 'gumtree':
                return $this->generateGumtreeLink($redirected_to, $main_cat, null);
                break;
            case 'lento':
                return $this->generateLentoLink($redirected_to, $main_cat, $announcement_title);
                break;
            case 'gazetapraca':
                return $this->generateGazetaPracaLink($redirected_to, $main_cat, null);
                break;
            case 'goldenline':
                return $this->generateGoldenLineLink($redirected_to, $main_cat, null);
                break;
            case 'infopraca':
                return $this->generateInfoPracaLink($redirected_to, $main_cat, null);
                break;
            case 'pracujpl':
                return $this->generatePracujPlLink($redirected_to, $main_cat, null);
                break;
            default:
                return '#';
                break;
        }
    }

    /**
     * @param $redirected_to
     * @param $main_cat
     * @param null $name
     * @return string
     */
    public function generateOlxLink($redirected_to, $main_cat, $name = null)
    {
//        $pattern    = '~http://olx.pl/nowe-ogloszenie/confirmpage/(.*?)/activate/~';
//        $link_part1 = 'http://olx.pl/oferta/CID';
//        $link_part2 = '-ID';
//        $link_part3 = '.html';
//
        //$pattern    = '~class="tdnone marginright5" title="' . $name . '" href="(.*?)" target="_blank">~';
        $pattern = '~class="tdnone marginright5" title="' . $name . '"\s+href="(.*?)"\s+target="_blank">\s+<i data-icon="preview"~';

        $html = $this->sendGet($redirected_to);

        preg_match_all(
            $pattern,
            (string)$html,
            $codeLink
        );

        return $codeLink[1][0];
    }

    /**
     * @param $redirected_to
     * @param null $main_cat
     * @param null $announcement_title
     * @return mixed
     */
    public function generateGumtreeLink($redirected_to, $main_cat = null, $announcement_title = null)
    {

        /*$list_link = 'https://www.gumtree.pl/my/ads.html';//'https://www.gumtree.pl/login.html'
        $this->logintoGumtree();
        $check = $this->sendPost('https://www.gumtree.pl/my/ads.html',[

        ],[
            'Host: www.gumtree.pl',
            'Referer: https://www.gumtree.pl/my/promote.html',
            'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0'
        ]);

        //$pattern = '~http://www.gumtree.pl/postConfirm.html?adId=1001717766540910993091909&sig=BCA7287E1C018BAF2572~';
        //http://www.gumtree.pl/a-szukam-kursu-lekcji-korepetycji/krak%C3%B3w/programista-tester/1001717766540910993091909?activateStatus=pendingAdActivateSuccess
        //link - http://www.gumtree.pl/a-szukam-kursu-lekcji-korepetycji/krakow/programista-tester/1001717766540910993091909
        //id? - 1001717766540910993091909
        $pattern = '~http://www.gumtree.pl/postConfirm.html?adId=(.*?)~';*/
        return $redirected_to;
    }

    /**
     * @param $redirected_to
     * @param null $main_cat
     * @param $announcement_title
     * @return string
     */
    public function generateLentoLink($redirected_to, $main_cat = null, $announcement_title)
    {
        $check = $this->sendGet('http://www.lento.pl/moje-ogloszenia.html');
        //$pattern = '~</div></td><td class="text-l"><div class="title"><a href="(.*?)" ><span>(.*?)</span></a></div><div class="mobile"> <a href="javascript:confirmaddel~';
        $pattern = '~</td><td class="hidden-xs"><div class="nphoto"></div></td><td class="description"><a href="(.*?)" class="title "><span>~';

        ////api.spoldzielnia.nsaudience.pl/backend/api/sendData.js?eid=992fe1f3-6fe6-c129-d7f4-0cd041826069&time=1469100207659&sourceId=lento.pl&url=http%3A%2F%2Fwww.lento.pl%2Fdodaj-ogloszenie.html%3Fstep%3Dverify&user_agent=Mozilla%2F5.0%20(X11%3B%20Ubuntu%3B%20Linux%20x86_64%3B%20rv%3A47.0)%20Gecko%2F20100101%20Firefox%2F47.0&domain=www.lento.pl

        preg_match_all(
            $pattern,
            (string)$check,
            $ann_links
        );

        if ($ann_links[1][0] !== null) {
//        if ($ann_links[2] !== null) {
//            $last_item = sizeof($ann_links[2]) - 1;
//            if (trim(strtolower($ann_links[2][$last_item])) == strtolower(trim($announcement_title))) {
//                return $ann_links[1][$last_item];
//            } else {
//                return 'http://www.lento.pl/moje-ogloszenia.html';
//            }
            return $ann_links[1][0];
        } else {
            return 'http://www.lento.pl/moje-ogloszenia.html';
        }
    }

    /**
     * @param $redirected_to
     * @param null $main_cat
     * @param null $announcement_title
     * @return mixed
     */
    public function generateGazetaPracaLink($redirected_to, $main_cat = null, $announcement_title = null)
    {
        return $redirected_to;
    }

    /**
     * @param $redirected_to
     * @param null $main_cat
     * @param null $announcement_title
     * @return mixed
     */
    public function generateGoldenLineLink($redirected_to, $main_cat = null, $announcement_title = null)
    {
        return $redirected_to;
    }

    /**
     * @param $redirected_to
     * @param null $main_cat
     * @param null $announcement_title
     * @return mixed
     */
    public function generateInfoPracaLink($redirected_to, $main_cat = null, $announcement_title = null)
    {
        return $redirected_to;
    }

    /**
     * @param $redirected_to
     * @param null $main_cat
     * @param null $announcement_title
     * @return mixed
     */
    public function generatePracujPlLink($redirected_to, $main_cat = null, $announcement_title = null)
    {
        return $redirected_to;
    }

    /**
     * @param Announcement2 $announcement
     * @param Portal $portal
     * @param $opt
     * @return string
     */
    private function sendOfferToInternalPortal($announcement, $portal, $opt)
    {
        $job_type = 'praca za granicą';
        if (isset($announcement->country->name) AND $announcement->country->name == Country::COUNTRY_POL) {
            $job_type = 'praca w Polsce';
        }

        $userEmail = Yii::$app->user->identity->email;

        $apply_link = $announcement->getApplyLink($portal->id, $portal->id);

        // na razie zakomentowane bo WP nie zwraca linku do ogłoszenia wystawionego w przyszłości
        // $date_start = $announcement->recruitment->date_start;
        $date_start = date('Y-m-d');

        $offerData = [
            "hash"            => $announcement->hash,
            "name"            => $announcement->title,
            "description"     => $announcement->header_part_1 . "\n<br/>" . $announcement->header_part_2,
            "country"         => $announcement->country->name,
            "city"            => $announcement->city,
            "date_start"      => $date_start,
            "date_end"        => $announcement->recruitment->date_end,
            "salary"          => $announcement->salary,
//            "work_type"       => $announcement->recruitment->workTypePretty,
            "work_date_start" => $announcement->recruitment->work_date_start,
            "work_date_end"   => $announcement->recruitment->work_date_end,
            "vacancies"       => $announcement->vacancies,
            "requirements"    => $announcement->requirements,
            "department"      => null,
            "apply_link"      => $apply_link,
            "recruitment"     => null,
            "profession"      => $announcement->profession->name,
            "state"           => $announcement->state->name,
            "education_title" => $announcement->minEducationTitle->name,
            "contract"        => $announcement->contract->name,
            "languages"       => null,
            "job_type"        => $job_type,
            "job_category"    => "produkcja", // branża tutaj
            "image_link"      => $portal->extra_link,
            "email"           => $userEmail,
            "content"         => $this->renderDescription($portal->id, $announcement, $opt, true)
        ];

        $offers[] = $offerData;

        $wpResponseContent = $this->sendHttpRequest($portal->post_link, $offers, 'PUT')->content;

        return json_decode($wpResponseContent);
    }

    /**
     * @param string $url Url to send request
     * @param array $data
     * @param string $http_method HTTP method
     * @param string $format
     * @return \yii\httpclient\Response
     */
    private function sendHttpRequest($url, $data, $http_method = 'PUT', $format = HttpClient::FORMAT_JSON)
    {
        $client   = new HttpClient();
        $response = $client->createRequest()
            ->setFormat($format)
            ->setMethod($http_method)
            ->setUrl($url)
            ->setData($data)
            ->send();

        return $response;
    }

    /**
     * @param $site
     * @return int|null
     */
    private function getSiteIdByName($site)
    {
        switch ($site) {
            case 'olx':
                return Portal::OLX_ID;
                break;
            case 'gumtree':
                return Portal::GUMTREE_ID;
                break;
            case 'lento':
                return Portal::LENTO_ID;
                break;
            case 'goldenline':
                return Portal::GOLDENLINE_ID;
                break;
            case 'gazetapraca':
                return Portal::GAZETAPRACA_ID;
                break;
            case 'infopraca':
                return Portal::INFOPRACA_ID;
                break;
            case 'pracujpl':
                return Portal::PRACUJPL_ID;
                break;
            case 'interimax':
                return Portal::INTERIMAX_ID;
                break;
            case 'ateam':
                return Portal::ATEAM_ID;
                break;
            default:
                return null;
                break;
        }
    }
}
