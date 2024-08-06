<?php

namespace jszsl\pay;

use think\Exception;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Formatter;

class  WechatPayClient
{


    private static $config;


    /**
     * @微信扫码支付
     *
     * @param $subject :主题
     * @param $outTradeNo :订单号
     * @param $totalAmount :付款金额
     */
    public static function scanPay($param)
    {
        $instance = self::getInstance();
        $resp = $instance -> chain('v3/pay/transactions/native')
            -> post(['json' => [
                'mchid' => self::$config['merchant_id'],
                'out_trade_no' => $param['outTradeNo'],
                'appid' => self::$config['appid'],
                'description' => $param['subject'],
                'notify_url' => $param['notify_url'],
                'amount' => [
                    'total' => $param['totalAmount'] * 100,
                    'currency' => 'CNY'
                ],
            ]]);

        $result = json_decode($resp -> getBody() -> getContents(), true);

        return $result;
    }


    /**
     * 签证签名并解密数据
     * @param['headers']:请求头
     * @param['body']:请求体
     */
    public static function verifyNotify($param)
    {
        $config = config('pay.wechat');

        $headers = $param['headers'];

        $inWechatpaySignature = $headers['wechatpay-signature'];// 请根据实际情况获取
        $inWechatpayTimestamp = $headers['wechatpay-timestamp'];// 请根据实际情况获取
        $inWechatpaySerial = $headers['wechatpay-serial'];// 请根据实际情况获取
        $inWechatpayNonce = $headers['wechatpay-nonce'];// 请根据实际情况获取
        $inBody = file_get_contents('php://input');// 请根据实际情况获取，例如: file_get_contents('php://input');
        $apiv3Key = $config['api_v3_key'];// 在商户平台上设置的APIv3密钥

        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformPublicKeyInstance = Rsa::from(file_get_contents($config['platform_certificate_file_dir'] . 'wechatpay_' . $inWechatpaySerial . '.pem'), Rsa::KEY_TYPE_PUBLIC);

        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
//        file_put_contents('callback.log', json_encode($param) . PHP_EOL, FILE_APPEND);
//        file_put_contents('debug.log', Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody) . PHP_EOL, FILE_APPEND);
//        file_put_contents('verifiedStatus.log', ($verifiedStatus ? 1 : 0) . PHP_EOL, FILE_APPEND);
        if ($timeOffsetStatus && $verifiedStatus) {
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray = (array)json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext' => $ciphertext,
                'nonce' => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $apiv3Key, $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = (array)json_decode($inBodyResource, true);

            if ($inBodyResourceArray['trade_state'] != 'SUCCESS') {
                return false;
            }
            return $inBodyResourceArray;
        }

        return false;
    }


    /**
     * 初次下载平台证书
     */
    public static function firstDownLoadCert()
    {
        $certList = WechatPlatformCertManager::getPlatformCertList();
        if (empty($certList)) {
            $config = config('pay.wechat');
            // 生成证书下载命令，避免硬编码，增加灵活性
            $command = "php " . root_path() . "vendor/bin/CertificateDownloader.php " .
                "-k {$config['api_v3_key']} -m {$config['merchant_id']} " .
                "-f {$config['merchant_private_key_file_path']} " .
                "-s {$config['merchant_certificate_serial']} " .
                "-o {$config['platform_certificate_file_dir']}";
            // 日志记录命令，而非直接输出
            // log_info("Executing command: {$command}"); // 假设log_info是记录信息的日志函数
            exec($command, $output, $return_var);
            $cert_files = glob($config['platform_certificate_file_dir'] . '/wechatpay_*.pem');
            if (count($cert_files) < 1) {
                throw new Exception('微信支付证书下载失败');
            }
        }
    }


    /**
     * 更新平台证书
     */
    public static function certificates()
    {
        // 增加缓存,控制请求频率
        if (cache('wechat_certificates_update_time') && time() - cache('wechat_certificates_update_time') < 7200) {
            return true;
        }

        $instance = self::getInstance();

        try {
            // 发送请求
            $resp = $instance -> chain('v3/certificates') -> get();
            // 校验响应状态
            if ($resp -> getStatusCode() !== 200) {
                throw new Exception("Unexpected status code: " . $resp -> getStatusCode());
            }

            $respBody = json_decode($resp -> getBody() -> getContents(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON decode error: " . json_last_error_msg());
            }

            $certList = $respBody['data'] ?? [];
            if (empty($certList)) {
                // 处理空数组或数据不存在的情况
                return false;
            }
            WechatPlatformCertManager::deleteOldCertFiles(self::$config);
            foreach ($certList as $cert) {
                try {
                    $aesUtil = new AesUtil(self::$config['api_v3_key'] ?? '');
                    // 解密数据
                    $data = $aesUtil -> decryptToString(
                        $cert['encrypt_certificate']['associated_data'],
                        $cert['encrypt_certificate']['nonce'],
                        $cert['encrypt_certificate']['ciphertext']
                    );
                    // 更新证书（假设这是一个本地操作，如果不是，应考虑异步处理以提升性能）
                    WechatPlatformCertManager::getPlatformCertList($cert['serial_no'], $data);
                } catch (\Exception $e) {
                    continue;
                }
            }
            // 更新缓存
            cache('wechat_certificates_update_time', time());
        } catch (Exception $e) {
            return false;
        }

        return true;
    }


    /**
     * 获取支付实例
     */
    private static function getInstance()
    {
        // 首次下载平台证书
        self::firstDownLoadCert();

        $config = config('pay.wechat');
        self::$config = $config;

        // 商户号
        $merchantId = self::$config['merchant_id'];
        // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
        $merchantPrivateKeyFilePath = file_get_contents(self::$config['merchant_private_key_file_path']);
        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
        // 「商户API证书」的「证书序列号」
        $merchantCertificateSerial = self::$config['merchant_certificate_serial'];
        // 构造一个 APIv3 客户端实例
        $instance = Builder::factory([
            'mchid' => $merchantId,
            'serial' => $merchantCertificateSerial,
            'privateKey' => $merchantPrivateKeyInstance,
            'certs' => WechatPlatformCertManager::getPlatformCertList(),
        ]);
        return $instance;
    }


}


