<?php
/*
Plugin Name: AI 评论回复
Version: 1.0
Plugin URL:
Description: 使用 AI 自动回复评论,管理员可以配置 AI 回复账号和自定义回复设置。
Author: 属余
Author URL: https://www.emlog.net/author/index/858
*/

!defined('EMLOG_ROOT') && exit('access denied!');

// 将插件函数挂载到合适的位置
// 管理菜单
//addAction('adm_menu', 'ai_comment_reply_menu');
// 管理设置页面
addAction('adm_head', 'ai_comment_reply_css');
// 评论保存 - 触发 AI 回复
addAction('comment_saved', 'ai_comment_reply_trigger');
// 评论显示钩子 - 用于自定义头像
addAction('get_Gravatar', 'ai_comment_custom_avatar');

/**
 * 将插件添加到管理菜单
 */
// function ai_comment_reply_menu() {
//     echo '<div class="sidebar-item"><a href="./plugin.php?plugin=ai_comment_reply"><i class="icofont-robot"></i>AI评论回复</a></div>';
// }

/**
 * 为插件添加自定义 CSS
 */
function ai_comment_reply_css() {
    echo '<style>
    .ai-settings-form {
        max-width: 800px;
        margin-bottom: 20px;
    }
    .ai-settings-section {
        background: #fff;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .ai-settings-section h3 {
        margin-top: 0;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    .ai-toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
        margin-right: 10px;
    }
    .ai-toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .ai-toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 24px;
    }
    .ai-toggle-slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    input:checked + .ai-toggle-slider {
        background-color: #2196F3;
    }
    input:checked + .ai-toggle-slider:before {
        transform: translateX(26px);
    }
    .ai-avatar-preview {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #eee;
        margin-top: 10px;
    }
    .nav-tabs .nav-link.active {
        font-weight: bold;
        border-bottom: 2px solid #2196F3;
    }
    </style>';
}

/**
 * 自定义 AI 头像
 * 
 * @param string $email 评论者邮箱
 * @param string $gravatar_url 原始的 Gravatar URL
 * @return string 可能被修改的头像 URL
 */
function ai_comment_custom_avatar($email, &$gravatar_url) {
    $plugin_storage = Storage::getInstance('ai_comment_reply');
    $settings = $plugin_storage->getValue('settings');
    
    // 确保设置存在
    if (empty($settings)) {
        return $gravatar_url;
    }
    
    // 检查是否启用自定义头像并且邮箱匹配
    if (isset($settings['use_custom_avatar']) && 
        $settings['use_custom_avatar'] && 
        !empty($settings['custom_avatar']) && 
        isset($settings['ai_email']) && 
        $email == $settings['ai_email']) {
        
        // 返回自定义头像 URL
        $gravatar_url = $settings['custom_avatar'];
    }
    
    return $gravatar_url;
}

/**
 * 当新评论保存时触发 AI 回复
 * 
 * @param int $commentId 新保存的评论 ID
 */
function ai_comment_reply_trigger($commentId) {
    // 获取插件设置
    $plugin_storage = Storage::getInstance('ai_comment_reply');
    $settings = $plugin_storage->getValue('settings');
    
    // 如果设置不存在或自动回复已禁用，退出
    if (empty($settings) || !$settings['auto_reply_enabled']) {
        return;
    }
    
    // 获取评论数据
    $db = MySql::getInstance();
    $comment = $db->once_fetch_array("SELECT * FROM " . DB_PREFIX . "comment WHERE cid = " . $commentId);
    
    // 跳过对现有评论的回复（仅处理新评论线程）
    if ($comment['pid'] > 0) {
        return;
    }
    
    // 如果配置了跳过管理员评论，则检查
    if ($settings['skip_admin_comments'] && $comment['uid'] > 0) {
        $user = $db->once_fetch_array("SELECT * FROM " . DB_PREFIX . "user WHERE uid = " . $comment['uid']);
        if ($user && $user['role'] == 'admin') {
            return;
        }
    }
    
    // 检查关键词过滤
    if (!empty($settings['keywords_to_skip'])) {
        $keywords = explode(',', $settings['keywords_to_skip']);
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (!empty($keyword) && stripos($comment['comment'], $keyword) !== false) {
                return; // 包含要跳过的关键词
            }
        }
    }
    
    // 获取文章内容作为上下文
    $article = $db->once_fetch_array("SELECT * FROM " . DB_PREFIX . "blog WHERE gid = " . $comment['gid']);
    if (!$article) {
        return;
    }
    
    // 准备 AI 提示
    $prompt = generate_ai_prompt($comment, $article, $settings);
    
    // 处理延迟回复（如果设置了）
    if (!empty($settings['reply_delay']) && $settings['reply_delay'] > 0) {
        sleep(min($settings['reply_delay'], 10)); // 最多延迟 10 秒，避免超时
    }
    
    // 调用 AI 获取回复
    try {
        $ai_response = AI::chat($prompt);
        
        if (!empty($ai_response)) {
            // 创建 AI 回复
            create_ai_reply($comment, $ai_response, $settings);
        }
    } catch (Exception $e) {
        // 记录错误
        error_log('AI 评论回复错误: ' . $e->getMessage());
    }
}

/**
 * 根据评论和文章上下文生成 AI 提示
 */
function generate_ai_prompt($comment, $article, $settings) {
    $comment_content = $comment['comment'];
    $article_title = $article['title'];
    $article_content = subContent($article['content'], 500, 1); // 获取内容的前 500 个字符
    
    $system_prompt = !empty($settings['system_prompt']) 
        ? $settings['system_prompt'] 
        : "你是博客文章评论区的助手，负责回复评论。保持回复简短、友好，并与评论内容相关。";
    
    $prompt = $system_prompt . "\n\n";
    $prompt .= "博客文章标题: " . $article_title . "\n";
    $prompt .= "博客文章摘要: " . $article_content . "\n\n";
    $prompt .= "评论内容: " . $comment_content . "\n\n";
    $prompt .= "请以博客管理员的身份，写一个简短友好的回复。";
    
    if (!empty($settings['response_style'])) {
        $style_map = [
            'friendly' => '友好',
            'professional' => '专业',
            'humorous' => '幽默',
            'empathetic' => '共情',
            'concise' => '简洁'
        ];
        $style = isset($style_map[$settings['response_style']]) ? $style_map[$settings['response_style']] : $settings['response_style'];
        $prompt .= "使用" . $style . "的语气。";
    }
    
    if (!empty($settings['max_length']) && is_numeric($settings['max_length'])) {
        $prompt .= "保持回复在 " . $settings['max_length'] . " 个字符以内。";
    } else {
        $prompt .= "保持回复简短明了。";
    }
    
    return $prompt;
}

/**
 * 创建 AI 回复评论
 */
function create_ai_reply($parent_comment, $ai_response, $settings) {
    $db = MySql::getInstance();
    
    // 获取 AI 账号信息
    $ai_name = !empty($settings['ai_name']) ? $settings['ai_name'] : "AI 助手";
    $ai_email = !empty($settings['ai_email']) ? $settings['ai_email'] : "ai@example.com";
    $ai_url = !empty($settings['ai_url']) ? $settings['ai_url'] : "";
    
    // 准备回复数据
    $reply_data = array(
        'gid' => $parent_comment['gid'],
        'pid' => $parent_comment['cid'],
        'date' => time(),
        'poster' => $ai_name,
        'comment' => $ai_response,
        'mail' => $ai_email,
        'url' => $ai_url,
        'ip' => getIp(),
        'hide' => 'n',
        'uid' => 0, // AI 没有用户 ID
    );
    
    // 转义所有字符串值，防止 SQL 注入
    if (method_exists($db, 'escape_string')) {
        foreach ($reply_data as $key => $value) {
            if (!is_numeric($value)) {
                $reply_data[$key] = $db->escape_string($value);
            }
        }
    }
    
    // 使用 SQL 语句插入 AI 回复
    $sql = "INSERT INTO " . DB_PREFIX . "comment(gid, pid, date, poster, comment, mail, url, ip, hide, uid) VALUES(
        {$reply_data['gid']},
        {$reply_data['pid']},
        {$reply_data['date']},
        '{$reply_data['poster']}',
        '{$reply_data['comment']}',
        '{$reply_data['mail']}',
        '{$reply_data['url']}',
        '{$reply_data['ip']}',
        '{$reply_data['hide']}',
        {$reply_data['uid']}
    )";
    
    // 执行插入
    $db->query($sql);
    
    // 更新文章评论计数
    $db->query("UPDATE " . DB_PREFIX . "blog SET comnum = comnum + 1 WHERE gid = " . $parent_comment['gid']);
    
    // 清除缓存
    $CACHE = Cache::getInstance();
    $CACHE->updateCache('comment');
    $CACHE->updateCache('sta');
    $CACHE->updateCache('recent_comments');
}

// 检查是否需要包含设置文件
if (isset($_GET['plugin']) && $_GET['plugin'] == 'ai_comment_reply') {
    include(EMLOG_ROOT . '/content/plugins/ai_comment_reply/ai_comment_reply_setting.php');
}