<?php

namespace Mix\Http;

use Mix\Core\Component;
use Mix\Helpers\RandomStringHelper;

/**
 * Session组件
 * @author LIUJIAN <coder.keda@gmail.com>
 */
class Session extends Component
{

    /**
     * 连接池
     * @var \Mix\Pool\ConnectionPoolInterface
     */
    public $pool;

    /**
     * 处理者
     * @var \Mix\Redis\RedisConnectionInterface
     */
    public $handler;

    /**
     * Key前缀
     * @var string
     */
    public $keyPrefix = 'SESSION:';

    /**
     * 生存时间
     * @var int
     */
    public $maxLifetime = 7200;

    /**
     * session名
     * @var string
     */
    public $name = 'session_id';

    /**
     * 过期时间
     * @var int
     */
    public $cookieExpires = 0;

    /**
     * 有效的服务器路径
     * @var string
     */
    public $cookiePath = '/';

    /**
     * 有效域名/子域名
     * @var string
     */
    public $cookieDomain = '';

    /**
     * 仅通过安全的 HTTPS 连接传给客户端
     * @var bool
     */
    public $cookieSecure = false;

    /**
     * 仅可通过 HTTP 协议访问
     * @var bool
     */
    public $cookieHttpOnly = false;

    /**
     * SessionKey
     * @var string
     */
    protected $_sessionKey = '';

    /**
     * SessionID
     * @var string
     */
    protected $_sessionId = '';

    /**
     * SessionID长度
     * @var int
     */
    protected $_sessionIdLength = 26;

    /**
     * 初始化事件
     */
    public function onInitialize()
    {
        parent::onInitialize(); // TODO: Change the autogenerated stub
        // 从连接池获取连接
        if (isset($this->pool)) {
            $this->handler = $this->pool->getConnection();
        }
    }

    /**
     * 请求前置事件
     */
    public function onRequestBefore()
    {
        parent::onRequestBefore();
        // 载入session_id
        $this->loadSessionId();
    }

    /**
     * 载入session_id
     */
    public function loadSessionId()
    {
        $this->_sessionId = \Mix::$app->request->cookie($this->name);
        if (is_null($this->_sessionId)) {
            // 创建session_id
            $this->_sessionId = RandomStringHelper::randomAlphanumeric($this->_sessionIdLength);
        }
        $this->_sessionKey = $this->keyPrefix . $this->_sessionId;
        // 延长session有效期
        $this->handler->expire($this->_sessionKey, $this->maxLifetime);
    }

    /**
     * 创建SessionId
     */
    public function createSessionId()
    {
        do {
            $this->_sessionId  = RandomStringHelper::randomAlphanumeric($this->_sessionIdLength);
            $this->_sessionKey = $this->keyPrefix . $this->_sessionId;
        } while ($this->handler->exists($this->_sessionKey));
    }

    /**
     * 赋值
     * @param $name
     * @param $value
     * @return bool
     */
    public function set($name, $value)
    {
        $success = $this->handler->hmset($this->_sessionKey, [$name => serialize($value)]);
        $this->handler->expire($this->_sessionKey, $this->maxLifetime);
        $success and \Mix::$app->response->setCookie($this->name, $this->_sessionId, $this->cookieExpires, $this->cookiePath, $this->cookieDomain, $this->cookieSecure, $this->cookieHttpOnly);
        return $success ? true : false;
    }

    /**
     * 取值
     * @param null $name
     * @return mixed|null
     */
    public function get($name = null)
    {
        if (is_null($name)) {
            $result = $this->handler->hgetall($this->_sessionKey);
            foreach ($result as $key => $item) {
                $result[$key] = unserialize($item);
            }
            return $result ?: [];
        }
        $value = $this->handler->hget($this->_sessionKey, $name);
        return $value === false ? null : unserialize($value);
    }

    /**
     * 判断是否存在
     * @param $name
     * @return bool
     */
    public function has($name)
    {
        $exist = $this->handler->hexists($this->_sessionKey, $name);
        return $exist ? true : false;
    }

    /**
     * 删除
     * @param $name
     * @return bool
     */
    public function delete($name)
    {
        $success = $this->handler->hdel($this->_sessionKey, $name);
        return $success ? true : false;
    }

    /**
     * 清除session
     * @return bool
     */
    public function clear()
    {
        $success = $this->handler->del($this->_sessionKey);
        return $success ? true : false;
    }

    /**
     * 获取SessionId
     * @return string
     */
    public function getSessionId()
    {
        return $this->_sessionId;
    }

}
