<?php
/**
 * API Key 加密/解密工具
 * 
 * 使用 OpenSSL AES-256-CBC 对敏感配置进行加密存储
 * 加密密钥基于 AUTH_SALT + DB_NAME 派生，服务器唯一
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
 */
namespace ZuoAIPlus\Utils;

if (!defined('ABSPATH')) exit;

class Crypto
{
    private static function getEncryptionKey(): string
    {
        // 基于 WordPress AUTH_SALT + DB_NAME 派生 32 字节密钥
        // AUTH_SALT 在 wp-config.php 中定义，每个站点唯一
        $raw = defined('AUTH_SALT') ? AUTH_SALT : 'zuo-ai-plus-default-salt';
        $raw .= defined('DB_NAME') ? DB_NAME : 'wp';
        return hash('sha256', $raw, true); // 32 bytes for AES-256
    }

    /**
     * 加密字符串
     */
    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') return '';
        
        $key = self::getEncryptionKey();
        $iv = random_bytes(16); // AES-CBC 16 bytes IV
        $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            // 加密失败，返回原文（降级处理）
            return $plaintext;
        }
        
        // 格式：base64(iv + ciphertext)，带前缀标识
        return 'enc:' . base64_encode($iv . $encrypted);
    }

    /**
     * 解密字符串
     */
    public static function decrypt(string $ciphertext): string
    {
        if ($ciphertext === '') return '';
        
        // 未加密的数据直接返回（向后兼容）
        if (strpos($ciphertext, 'enc:') !== 0) {
            return $ciphertext;
        }
        
        $data = base64_decode(substr($ciphertext, 4));
        if ($data === false || strlen($data) < 16) {
            return $ciphertext; // 损坏数据返回原文
        }
        
        $key = self::getEncryptionKey();
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        if ($decrypted === false) {
            return $ciphertext; // 解密失败返回原文
        }
        
        return $decrypted;
    }

    /**
     * 加密 API Keys 数组
     * 只加密 api_key 字段，其他字段（model/base_url/image_model）不加密
     */
    public static function encryptApiKeys(array $apiKeys): array
    {
        $encrypted = [];
        foreach ($apiKeys as $k => $item) {
            if (is_array($item)) {
                $encrypted[$k] = [
                    'api_key'     => isset($item['api_key']) ? self::encrypt($item['api_key']) : '',
                    'model'       => $item['model'] ?? '',
                    'base_url'    => $item['base_url'] ?? '',
                    'image_model' => $item['image_model'] ?? '',
                ];
                // 保留旧格式中可能存在的其他键
                foreach ($item as $key => $val) {
                    if (!isset($encrypted[$k][$key])) {
                        $encrypted[$k][$key] = $val;
                    }
                }
            } elseif (is_string($item)) {
                // 旧格式：直接是 API key 字符串
                $encrypted[$k] = self::encrypt($item);
            } else {
                $encrypted[$k] = $item;
            }
        }
        return $encrypted;
    }

    /**
     * 解密 API Keys 数组
     */
    public static function decryptApiKeys(array $apiKeys): array
    {
        $decrypted = [];
        foreach ($apiKeys as $k => $item) {
            if (is_array($item)) {
                $decrypted[$k] = [
                    'api_key'     => isset($item['api_key']) ? self::decrypt($item['api_key']) : '',
                    'model'       => $item['model'] ?? '',
                    'base_url'    => $item['base_url'] ?? '',
                    'image_model' => $item['image_model'] ?? '',
                ];
                foreach ($item as $key => $val) {
                    if (!isset($decrypted[$k][$key])) {
                        $decrypted[$k][$key] = $val;
                    }
                }
            } elseif (is_string($item)) {
                // 旧格式
                $decrypted[$k] = self::decrypt($item);
            } else {
                $decrypted[$k] = $item;
            }
        }
        return $decrypted;
    }
}
