<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\BaseController;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Yansongda\LaravelPay\Facades\Pay;

/**
 * 这个是头条小程序调起支付宝的一个后端demo。
 * 以此示例可以让众多头条小程序接入者，少走弯路。
 * 此示例是基于Laravel框架开发。
 * Thinks!!
 */
class HomeController extends BaseController
{
  protected $time;
  public function __construct()
  {
    parent::__construct();
    $this->time = (string)time();
  }

  // 1、先走登陆接口拿到用户的openid
  public function login(Request $request)
  {
    $code = $request->post('code');
    // 使用了GuzzleHttp\Client插件
    $http = new Client();
    $response = $http->get('https://developer.toutiao.com/api/apps/jscode2session', [
      'query' => [
        'appid' => 'ttxxxxxxxxxxxxxxxx',
        'secret' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'code' => $code
      ],
      'verify' => false
    ]);
    $data = json_decode($response->getBody(), true);
    // 缓存openid
    Cache::set('openid', $data['openid']);
    // 这个方法是将数据返回给前端，请自行封装
    return $this->setParams($data)->success();
  }

  // 2、订单创建接口 返回前端需要的所有数据
  public function createOrder()
  {
    // 创建头条的订单
    $tradeNo = $this->_getTradeNo();
    // 创建支付宝的订单，返回支付宝的url，走app的sdk
    $aliPayUrl = $this->_getAliPayUrl();
    $data = [
      'app_id' => '80009xxxxxxx',
      'method' => 'tp.trade.confirm',
      'sign_type' => 'MD5',
      'timestamp' => $this->time,
      'trade_no' => $tradeNo,
      'merchant_id' => '190xxxxxxx',
      'uid' => Cache::get('openid'),
      'total_amount' => 1,
      'pay_channel' => 'ALIPAY_NO_SIGN',
      'pay_type' => 'ALIPAY_APP',
      'risk_info' => json_encode([
        'ip' => \Request::getClientIp()
      ]),
      'params' => json_encode([
        'url' => $aliPayUrl
      ])
    ];
    // 最后一步获取签名
    $data['sign'] = $this->_getSign($data);
    // 返回调起支付需要的所有参数
    return $this->setParams($data)->success();
  }

  public function _getSign($params)
  {
    $arr = [
      'app_id' => $params['app_id'],
      'sign_type' => $params['sign_type'],
      'timestamp' => $params['timestamp'],
      'trade_no' => $params['trade_no'],
      'merchant_id' => $params['merchant_id'],
      'uid' => $params['uid'],
      'total_amount' => $params['total_amount'],
      'params' => $params['params']
    ];
    $signList = collect($arr)->map(function ($value, $key) {
      return $key.'='.$value;
    })->sort()->implode('&');
    return md5($signList.'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
  }

  public function aliPay()
  {
    $data = Pay::alipay()->app([
      'out_trade_no' => $this->time,
      'total_amount' => 0.01,
      'subject' => '测试'
    ]);
    return $data;
  }

  public function _getAliPayUrl()
  {
    $http = new Client();
    // 这里实际是走aliPay()方法
    $response = $http->post('http://192.168.13.63/api/aliPay', [
      'headers' => [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json'
      ],
      'verify' => false
    ]);
    return (string)$response->getBody();
  }

  public function _getTradeNo()
  {
    $bizContent = [
      'out_order_no' => $this->time,
      'uid' => Cache::get('openid'),
      'merchant_id' => '190xxxxxxx',
      'total_amount' => 1,
      'currency' => 'CNY',
      'subject' => '测试商户',
      'body' => '测试订单',
      'valid_time' => 600,
      'trade_time' => $this->time,
      'notify_url' => 'https://xxx.xxxxxx.com',
      'risk_info' => [
        'ip' => \Request::getClientIp()
      ]
    ];
    $payload = [
      'format' => 'JSON',
      'app_id' => '80009xxxxxxx',
      'charset' => 'utf-8',
      'sign_type' => 'MD5',
      'timestamp' => $this->time,
      'version' => '1.0',
      'merchant_id' => '190xxxxxxx',
      'uid' => Cache::get('openid'),
      'biz_content' => json_encode($bizContent),
      'method' => 'tp.trade.create'
    ];
    $signList = collect($payload)->map(function ($value, $key) {
      return $key.'='.$value;
    })->sort()->implode('&');
    $payload['sign'] = md5($signList.'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

    $http = new Client();
    $response = $http->post('http://tp-pay.snssdk.com/gateway', [
      'query' => $payload,
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded'
      ],
      'verify' => false
    ]);
    $data = json_decode($response->getBody(), true);
    return $data['response']['trade_no'];
  }
}
