<?php
/**
 * AI 评论回复插件回调函数
 */
!defined('EMLOG_ROOT') && exit('access denied!');

/**
 * 插件激活时调用的函数
 */
function callback_init() {
    // 初始化插件设置
    $plugin_storage = Storage::getInstance('ai_comment_reply');
    $settings = $plugin_storage->getValue('settings');
    
    // 如果设置不存在，创建默认设置
    if (empty($settings)) {
        $default_settings = array(
            'auto_reply_enabled' => true,
            'skip_admin_comments' => true,
            'ai_name' => 'AI 助手',
            'ai_email' => 'ai@example.com',
            'ai_url' => '',
            'use_custom_avatar' => false,
            'custom_avatar' => '',
            'system_prompt' => '你是博客文章评论区的助手，负责回复评论。保持回复简短、友好，并与评论内容相关。',
            'response_style' => 'friendly',
            'max_length' => 300,
            'keywords_to_skip' => '',
            'reply_delay' => 0,
        );
        
        $plugin_storage->setValue('settings', $default_settings, 'array');
    } else {
        // 确保所有必要的设置键都存在
        $updated = false;
        
        if (!isset($settings['use_custom_avatar'])) {
            $settings['use_custom_avatar'] = false;
            $updated = true;
        }
        
        if (!isset($settings['custom_avatar'])) {
            $settings['custom_avatar'] = '';
            $updated = true;
        }
        
        if (!isset($settings['keywords_to_skip'])) {
            $settings['keywords_to_skip'] = '';
            $updated = true;
        }
        
        if (!isset($settings['reply_delay'])) {
            $settings['reply_delay'] = 0;
            $updated = true;
        }
        
        if ($updated) {
            $plugin_storage->setValue('settings', $settings, 'array');
        }
    }
    
    // 创建上传目录
    $upload_path = EMLOG_ROOT . '/content/plugins/ai_comment_reply/uploads/';
    if (!is_dir($upload_path)) {
        @mkdir($upload_path, 0777, true);
    }
    
    // 激活后显示欢迎消息
    echo '<div class="alert alert-success">AI 评论回复插件已成功激活！请前往插件设置页面进行配置。</div>';
}

/**
 * 插件删除时调用的函数
 */
function callback_rm() {
    // 清理所有插件数据
    $plugin_storage = Storage::getInstance('ai_comment_reply');
    $settings = $plugin_storage->getValue('settings');
    
    // 删除自定义头像文件
    if (!empty($settings['custom_avatar'])) {
        $avatar_path = parse_url($settings['custom_avatar'], PHP_URL_PATH);
        if (strpos($avatar_path, '/content/plugins/ai_comment_reply/uploads/') !== false) {
            $full_path = EMLOG_ROOT . $avatar_path;
            if (file_exists($full_path)) {
                @unlink($full_path);
            }
        }
    }
    
    // 删除上传目录
    $upload_path = EMLOG_ROOT . '/content/plugins/ai_comment_reply/uploads/';
    if (is_dir($upload_path)) {
        // 删除目录中的所有文件
        $files = glob($upload_path . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        // 尝试删除目录
        @rmdir($upload_path);
    }
    
    // 清理所有设置
    $plugin_storage->deleteAllName('YES');
    
    // 可选：删除所有 AI 生成的评论
    // 如果你希望在插件被删除时删除所有 AI 评论，取消下面代码的注释
    /*
    if (!empty($settings['ai_name'])) {
        $ai_name = $settings['ai_name'];
        $db = MySql::getInstance();
        
        // 使用合适的方法转义字符串，防止 SQL 注入
        $ai_name = $db->escape_string($ai_name);
        
        // 删除所有 AI 生成的评论
        $db->query("DELETE FROM " . DB_PREFIX . "comment WHERE poster = '$ai_name'");
        
        // 更新所有受影响文章的评论计数
        $db->query("UPDATE " . DB_PREFIX . "blog SET comnum = (
            SELECT COUNT(*) FROM " . DB_PREFIX . "comment WHERE gid = " . DB_PREFIX . "blog.gid AND hide = 'n'
        )");
        
        // 清除缓存
        $CACHE = Cache::getInstance();
        $CACHE->updateCache('comment');
        $CACHE->updateCache('sta');
        $CACHE->updateCache('recent_comments');
    }
    */
    
    echo '<div class="alert alert-success">AI 评论回复插件已成功删除！所有插件设置和上传文件已清除。</div>';
}

/**
 * 插件更新时调用的函数
 */
function callback_up() {
    // 检查是否需要更新任何设置结构
    $plugin_storage = Storage::getInstance('ai_comment_reply');
    $settings = $plugin_storage->getValue('settings');
    
    // 如果设置为空，创建默认设置
    if (empty($settings)) {
        callback_init();
        return;
    }
    
    // 添加可能在旧版本中不存在的任何新设置
    $updated = false;
    
    // 添加自定义头像相关设置
    if (!isset($settings['use_custom_avatar'])) {
        $settings['use_custom_avatar'] = false;
        $updated = true;
    }
    
    if (!isset($settings['custom_avatar'])) {
        $settings['custom_avatar'] = '';
        $updated = true;
    }
    
    if (!isset($settings['reply_delay'])) {
        $settings['reply_delay'] = 0;
        $updated = true;
    }
    
    if (!isset($settings['keywords_to_skip'])) {
        $settings['keywords_to_skip'] = '';
        $updated = true;
    }
    
    // 创建上传目录（如果不存在）
    $upload_path = EMLOG_ROOT . '/content/plugins/ai_comment_reply/uploads/';
    if (!is_dir($upload_path)) {
        @mkdir($upload_path, 0777, true);
    }
    
    // 如果需要，保存更新的设置
    if ($updated) {
        $plugin_storage->setValue('settings', $settings, 'array');
    }
    
    echo '<div class="alert alert-success">AI 评论回复插件已成功更新到最新版本！</div>';
}