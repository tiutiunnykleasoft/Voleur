<?phpclass Helper{    CONST REDIRECT_URI = 'https://voleur.000webhostapp.com/api/';    CONST CLIENT_SECRET = 'nUFC2TP8nzx1BDHuV2N7qulZDvoAwz2vSh3fuf3T';    CONST CLIENT_ID = '402';    CONST BASE_LOG = 'id12607894_admin_plus';    CONST BASE_PASS = 'RrTyYo2@pL2@k!';    CONST SCOPES =        'oauth-user-show'.    ','.'oauth-donation-subscribe'.    ','.'oauth-donation-index';    /**     * Connect to the db     *     * @param $base_log     * @param $base_pass     * @return PDO     */    public static function connect_db($base_log, $base_pass)    {        $conn = new PDO("mysql:host=localhost;dbname=id12607894_hidden_home", $base_log, $base_pass);        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);        return $conn;}    /**     * Insert token into the base     *     * @param PDO $connect     * @param array $values     * @param $user_code     */    public static function  insert_token(PDO $connect, array $values){        $sql = "INSERT INTO tokens (access_token, expired, refresh_token, user_code,admin_code) VALUES (?,?,?,?,?)";        $stmt= $connect->prepare($sql);        $user_code = self::uidGenerate($values['access_token']);        $admin_code = $user_code.'-'.uniqid();        $stmt->execute([$values['access_token'],$values['expires_in'],$values['refresh_token'],$user_code,$admin_code]);        return array('user' => $user_code,                    'admin' => $admin_code);    }    /**     * Get Token for Catch-server process     * Refresh token before     * Working with db     *     * @param PDO $connect     * @param $user_code     * @return array|mixed     */    public static function get_token(PDO $connect, $user_code){        $stmt = $connect->query("SELECT * FROM tokens WHERE user_code = '".$user_code."'");        $temp = $stmt->fetchAll();        $new_token = self::refresh_token($temp[0]['refresh_token']);        if ($new_token[0] == '200') {        $stmt = $connect->query("UPDATE tokens SET access_token='".$new_token['access_token']."', expired='".$new_token['expires_in']."', refresh_token='".$new_token['refresh_token']."' WHERE user_code = '".$user_code."'");        $stmt->execute();        } else {            $new_token = array(             'error' => $new_token            );        }        return $new_token;    }    public static function close_connection(PDO $connect){        return $connect = null;    }    /**     * Init Curl with your data     *     * @param $url     * @param $method     * @param $data     * @param null $token     * @return false|resource     */    public static function initCurl($url, $method, $data, $token = null){        $data = array_merge($data,array(            'client_id' => self::CLIENT_ID,            'client_secret' => self::CLIENT_SECRET        ));        $ch = curl_init($url);        curl_setopt($ch, CURLOPT_COOKIEJAR, '-');        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);        curl_setopt ($ch, CURLOPT_POST, true);        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);        if ($token != null) {        curl_setopt($ch, CURLOPT_HTTPHEADER, [            "Authorization: Bearer ".$token,        ]);        }        return $ch;    }    /**     * Make a GET Request     *     * @param $url     * @param $data     * @param $token     * @return array     */    public static function get($url,$data,$token){        $ch = self::initCurl($url,"GET",$data,$token);        $response = json_decode(curl_exec($ch),true);        $status_code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);        array_push($response,$status_code);        curl_close($ch);        return $response;    }    /**     * Make a POST request     *     * @param $url     * @param $data     * @return array     */    public static function post($url,$data){        $ch = self::initCurl($url,"POST",$data);        $response = json_decode(curl_exec($ch),true);        $status_code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);        array_push($response,$status_code);        curl_close($ch);        return $response;    }    public static function getName($token){        $url = "https://www.donationalerts.com/api/v1/user/oauth";        $response = Helper::get($url,null,$token);        if ($response[0] == 200){        return $response;        }    }    /**     * Seperate uuid to username and hash     *     * @param $crypted_code     * @return mixed     */    public static function deCryptUuidAdminName($crypted_code){        list($id,$hash) = explode("-",$crypted_code);        return $id;    }    /**     * Check if username exists     *     * @param $username     * @return bool     */    public static function checkUserHash($username){        $db = self::connect_db(self::BASE_LOG,self::BASE_PASS);        $result = $db->query("SELECT * FROM tokens")->fetchAll();        for ($i = 0; $i < count($result); $i++) {            if (password_verify($username,self::deCryptUuidAdminName($result[$i]['admin_code']))) {                 self::close_connection($db);                return false;            };        }        self::close_connection($db);        return true;    }    /**     * Generate hash-name using username     *     * @return string     */    public static function uidGenerate($token){        $name = self::getName($token)['data']['name'];        if (self::checkUserHash($name)){            $crypto_name = password_hash($name,PASSWORD_DEFAULT);            return $crypto_name;        } else {            echo json_encode(array("message" => "Token for this user already created", "error" => "Can't create token"));            exit;        }    }    /**     * Get token     *     * @param $code     */    public static function getcode($code){        $data = array_filter([            'grant_type' => 'authorization_code',            'redirect_uri' => self::REDIRECT_URI.'processor.php',            'code' => $code        ]);            $result = Helper::post('https://www.donationalerts.com/oauth/token',$data);            if ($result[0] == 200) {                $temp = self::connect_db(self::BASE_LOG,self::BASE_PASS);                $codes = self::insert_token($temp,$result);                self::close_connection($temp);                echo json_encode($codes,JSON_PRETTY_PRINT);exit;            } else {                echo "Error while get aceess code : message -> ";                print_r($result);                exit;            }    }    public static function get_donations($token) {        $url = 'https://www.donationalerts.com/api/v1/alerts/donations';        $response = Helper::get($url,null,$token);        if ($response[0] == 200){            return $response;        } else exit;    }    public static function refresh_token($refresher){        $data = array(            'grant_type' => 'refresh_token',            'refresh_token' => $refresher,            'scope' => 'oauth-user-show oauth-donation-subscribe oauth-donation-index',        );        $url = 'https://www.donationalerts.com/oauth/token';        return self::post($url,$data);    }}?>