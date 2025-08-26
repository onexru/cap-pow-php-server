<?php
/**
 * 2025-8-26
 * Cap-Pow PHP服务端，挑战令牌生成与验证
 * @小森 雾创岛 www.tr0.cn
 * 适配Cap-Pow官方0.0.6 JS版本，不带防重放攻击
 * 需要带重放攻击防护的版本请使用Cap-Pow的分支One-Pow 0.0.7版本
 */
class Cap
{
    private $config;
    private $pdo;
    private $db_Driver = '.data/cap.db';
    private $c = 64;
    private $s = 128;
    private $d = 4;

    public function __construct($configObj = null) {
        $this->config = [
            'db_Driver' => $this->db_Driver,
            'c' => $this->c,
            's' => $this->s,
            'd' => $this->d
        ];
        if ($configObj) {
            $this->config = array_merge($this->config, (array)$configObj);
        }
        // 初始化SQLite数据库
        $this->initDatabase();
    }

    /**
     * 初始化数据库
     */
    private function initDatabase()
    {
        try {
            // 检查数据库是否存在，如果不存在则创建
            $dbPath = $this->config['db_Driver'];
            if(!file_exists($dbPath) && !$this->isSQLiteFile($dbPath)) {
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
            }
            $this->pdo = new PDO("sqlite:" . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // 创建表
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS challenges (
                    token TEXT PRIMARY KEY,
                    data TEXT NOT NULL,
                    expires INTEGER NOT NULL,
                    used INTEGER DEFAULT 0,
                    created_at INTEGER DEFAULT 0
                );

                CREATE TABLE IF NOT EXISTS tokens (
                    token_key TEXT PRIMARY KEY,
                    data TEXT NOT NULL,
                    expires INTEGER NOT NULL,
                    used INTEGER DEFAULT 0,
                    created_at INTEGER DEFAULT 0
                );

                CREATE INDEX IF NOT EXISTS idx_challenges_expires ON challenges(expires);
                CREATE INDEX IF NOT EXISTS idx_tokens_expires ON tokens(expires);
            ");
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * 检查文件是否为SQLite数据库
     */
    private function isSQLiteFile($path)
    {
        if (!file_exists($path)) {
            return false;
        }
        $handle = fopen($path, 'rb');
        $header = fread($handle, 16);
        fclose($handle);
        return strpos($header, 'SQLite format 3') === 0;
    }

    /**
     * 生成挑战令牌
     */
    public function createChallenge($data = [], $expires = 300)
    {
        $token = bin2hex(random_bytes(25));
        $data = [
            'c' => $data['c'] ?? $this->config['c'],
            's' => $data['s'] ?? $this->config['s'],
            'd' => $data['d'] ?? $this->config['d']
        ];
        try {
            $stmt = $this->pdo->prepare("INSERT INTO challenges (token, data, expires, created_at) VALUES (:token, :data, :expires, :created_at)");
            $stmt->execute([
                ':token' => $token,
                ':data' => json_encode($data),
                ':expires' => time() + $expires,
                ':created_at' => time()
            ]);
            return [
                'challenge' => $data,
                'token' => $token,
                // 返回时需要转换为毫秒以便与前端保持一致
                'expires' => (time() + $expires) * 1000
            ];
        } catch (PDOException $e) {
            error_log("Failed to create challenge: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 验证挑战令牌
     */
    public function redeemChallenge($params = [])
    {
        $token = $params['token'] ?? null;
        $solutions = $params['solutions'] ?? null;
        if (!$token || !$solutions || !is_array($solutions)) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        // 获取挑战数据
        $stmt = $this->pdo->prepare("SELECT data, expires, used FROM challenges WHERE token = ?");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'message' => 'Challenge not found'];
        }
        $challengeData = json_decode($row['data'], true);
        // 挑战超时
        if ($row['expires'] < time()) {
            $this->pdo->prepare("DELETE FROM challenges WHERE token = ?")->execute([$token]);
            return ['success' => false, 'message' => 'Challenge expired'];
        }
        // 已使用
        if ($row['used']) {
            return ['success' => false, 'message' => 'Challenge already used'];
        }
        // 验证解决方案
        if(count($solutions) !== $challengeData['c']) {
            return ['success' => false, 'message' => 'Solution count mismatch'];
        }
        // 验证挑战方案
        $challengeCount = $challengeData['c'];
        for ($i = 1; $i <= $challengeCount; $i++) {
            $salt = $this->prng($token . $i, $challengeData['s']);
            $target = $this->prng($token . $i . 'd', $challengeData['d']);
            $solution = $solutions[$i - 1];

            if (!is_numeric($solution)) {
                return ['success' => false, 'message' => "Invalid solution at index " . ($i - 1)];
            }
            
            $hashInput = $salt . $solution;
            $hash = hash('sha256', $hashInput);
            
            if (strpos($hash, $target) !== 0) {
                return ['success' => false, 'message' => "Invalid solution at index " . ($i - 1)];
            }
        }
        // 标记为已使用
        $this->pdo->prepare("UPDATE challenges SET used = 1 WHERE token = ?")->execute([$token]);
        // 生成验证令牌
        $verToken = $this->generateVerificationToken($token);
        // 清理已使用的挑战
        $this->pdo->prepare("DELETE FROM challenges WHERE token = ?")->execute([$token]);
        // 返回成功数据
        return [
            'success' => true,
            'token' => $verToken['token'],
            'expires' => $verToken['expires'] * 1000 // 转为毫秒
        ];
    }

    /**
     * 验证token令牌
     */
    public function validateToken($token = null) {
        if (!$token || strpos($token, ':') === false) {
            return ['success' => false, 'message' => 'Invalid token format'];
        }
        list($id, $tokenPart) = explode(':', $token, 2);
        $stmt = $this->pdo->prepare("SELECT data, expires, used FROM tokens WHERE token_key = ?");
        $tokenKey = $id . ':' . hash('sha256', $tokenPart);
        $stmt->execute([$tokenKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'message' => 'Token not found'];
        }
        $tokenData = json_decode($row['data'], true);
        // 令牌超时
        if ($row['expires'] < time()) {
            $this->pdo->prepare("DELETE FROM tokens WHERE token_key = ?")->execute([$tokenKey]);
            return ['success' => false, 'message' => 'Token expired'];
        }
        // 已使用
        if ($row['used']) {
            return ['success' => false, 'message' => 'Token already used'];
        }
        // 标记为已使用
        $this->pdo->prepare("UPDATE tokens SET used = 1 WHERE token_key = ?")->execute([$tokenKey]);
        // 清理已使用的令牌
        $this->pdo->prepare("DELETE FROM tokens WHERE token_key = ?")->execute([$tokenKey]);
        return [
            'success' => true,
            'message' => 'Token validated successfully'
        ];
    }

    /**
     * 生成验证令牌
     */
    private function generateVerificationToken($originalToken) {
        $vertoken = bin2hex(random_bytes(15));
        $id = bin2hex(random_bytes(8));
        $expires = time() + 20 * 60; // 20分钟
        
        $tokenHash = hash('sha256', $vertoken);
        $tokenKey = $id . ':' . $tokenHash;
        
        $data = [
            'expires' => $expires,
            'created' => time(),
            'originalToken' => $originalToken,
            'vertoken' => $vertoken,
            'used' => false
        ];
        
        $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO tokens (token_key, data, expires, used) VALUES (?, ?, ?, 0)");
        $stmt->execute([$tokenKey, json_encode($data, JSON_UNESCAPED_UNICODE), $expires]);
        
        return [
            'token' => $id . ':' . $vertoken,
            'expires' => $expires
        ];
    }

    /**
     * 从字符串种子生成指定长度的确定性十六进制字符串
     */
    public function prng($seed, $length) {
        $state = $this->fnv1a($seed);
        $result = "";

        $next = function () use (&$state) {
            $state ^= ($state << 13) & 0xFFFFFFFF;
            $urShift17 = $this->unsignedRightShift($state, 17);
            $state ^= $urShift17;
            $state ^= ($state << 5) & 0xFFFFFFFF;
            return $state & 0xFFFFFFFF;
        };
    
        while (strlen($result) < $length) {
            $rnd = $next();
            $hex = str_pad(dechex($rnd), 8, '0', STR_PAD_LEFT);
            $result .= $hex;
        }
    
        return substr($result, 0, $length);
    }

    /**
     * FNV-1a 哈希算法实现
     */
    public function fnv1a($str) {
        $hash = 2166136261;
        $length = strlen($str);
        for ($i = 0; $i < $length; $i++) {
            $charCode = ord($str[$i]);
            $hash ^= $charCode;
            
            $shift1 = ($hash << 1) & 0xFFFFFFFF;
            $shift4 = ($hash << 4) & 0xFFFFFFFF;
            $shift7 = ($hash << 7) & 0xFFFFFFFF;
            $shift8 = ($hash << 8) & 0xFFFFFFFF;
            $shift24 = ($hash << 24) & 0xFFFFFFFF;
            
            $hash = ($hash + $shift1 + $shift4 + $shift7 + $shift8 + $shift24) & 0xFFFFFFFF;
        }
        return $hash;
    }

    /**
     * 模拟JavaScript的无符号右移操作
     */
    public function unsignedRightShift($value, $shift) {
        if ($shift < 0 || $shift > 31) {
            return 0; // 超出范围按0处理（与JavaScript一致）
        }
        // 先将值转为32位无符号整数
        $value = $value & 0xFFFFFFFF;
        // 无符号右移逻辑：若最高位为1（负数），需修正符号位影响
        if ($value & 0x80000000) {
            $value = ($value >> 1) & 0x7FFFFFFF; // 先右移1位并清最高位
            $value = $value >> ($shift - 1); // 继续右移剩余位数
        } else {
            $value = $value >> $shift; // 正数直接右移
        }
        return $value;
    }
}