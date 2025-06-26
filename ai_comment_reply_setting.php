<?php
!defined('EMLOG_ROOT') && exit('access denied!');

/**
 * 插件设置页面视图函数
 */
function plugin_setting_view() {
    // 获取已存储的设置
    $plugin_storage = Storage::getInstance('ai_comment_reply');
    $settings = $plugin_storage->getValue('settings');
    
    // 如果设置不存在，设置默认值
    if (empty($settings)) {
        $settings = array(
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
    }
    
    // 确保所有必要的键都存在，防止报错
    if (!isset($settings['use_custom_avatar'])) {
        $settings['use_custom_avatar'] = false;
    }
    if (!isset($settings['custom_avatar'])) {
        $settings['custom_avatar'] = '';
    }
    if (!isset($settings['keywords_to_skip'])) {
        $settings['keywords_to_skip'] = '';
    }
    if (!isset($settings['reply_delay'])) {
        $settings['reply_delay'] = 0;
    }
    
    // 处理表单提交
    if (isset($_POST['save_ai_settings'])) {
        $settings['auto_reply_enabled'] = isset($_POST['auto_reply_enabled']) ? true : false;
        $settings['skip_admin_comments'] = isset($_POST['skip_admin_comments']) ? true : false;
        $settings['ai_name'] = Input::postStrVar('ai_name', 'AI 助手');
        $settings['ai_email'] = Input::postStrVar('ai_email', 'ai@example.com');
        $settings['ai_url'] = Input::postStrVar('ai_url', '');
        $settings['use_custom_avatar'] = isset($_POST['use_custom_avatar']) ? true : false;
        $settings['custom_avatar'] = Input::postStrVar('custom_avatar', '');
        $settings['system_prompt'] = Input::postStrVar('system_prompt', '');
        $settings['response_style'] = Input::postStrVar('response_style', 'friendly');
        $settings['max_length'] = Input::postIntVar('max_length', 300);
        $settings['keywords_to_skip'] = Input::postStrVar('keywords_to_skip', '');
        $settings['reply_delay'] = Input::postIntVar('reply_delay', 0);
        
        // 处理头像上传
        if (!empty($_FILES['avatar_file']['name'])) {
            $upload_path = EMLOG_ROOT . '/content/plugins/ai_comment_reply/uploads/';
            
            // 创建上传目录（如果不存在）
            if (!is_dir($upload_path)) {
                @mkdir($upload_path, 0777, true);
            }
            
            $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
            $file_ext = strtolower(pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_types)) {
                $new_file_name = 'ai_avatar_' . time() . '.' . $file_ext;
                $destination = $upload_path . $new_file_name;
                
                if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $destination)) {
                    // 成功上传，更新设置
                    $settings['custom_avatar'] = BLOG_URL . 'content/plugins/ai_comment_reply/uploads/' . $new_file_name;
                    $settings['use_custom_avatar'] = true;
                } else {
                    // 上传失败
                    echo '<div class="alert alert-danger">头像上传失败，请检查目录权限。</div>';
                }
            } else {
                // 不允许的文件类型
                echo '<div class="alert alert-danger">只允许上传JPG、PNG和GIF格式的图片文件。</div>';
            }
        }
        
        // 保存设置
        $plugin_storage->setValue('settings', $settings, 'array');
        
        // 显示成功消息
        echo '<div class="alert alert-success">设置已保存！</div>';
    }
    
    // 测试 AI 功能
    $test_result = '';
    if (isset($_POST['test_ai'])) {
        $test_prompt = Input::postStrVar('test_prompt', '');
        if (!empty($test_prompt)) {
            try {
                // 检查 AI 是否正确配置
                $ai_config = AI::getCurrentModelInfo();
                if (!empty($ai_config['api_key'])) {
                    $test_result = AI::chat($test_prompt);
                } else {
                    $test_result = "错误：AI 未配置。请在系统设置中配置 AI 功能。";
                }
            } catch (Exception $e) {
                $test_result = "错误：" . $e->getMessage();
            }
        } else {
            $test_result = "请输入测试提示语。";
        }
    }
    
    // 验证 AI 配置
    $ai_config = AI::getCurrentModelInfo();
    $ai_configured = !empty($ai_config['api_key']);
    
    // 获取当前活动的标签页
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
    
    ?>
    
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">AI 评论回复设置</h1>
    </div>
    
    <?php if (!$ai_configured): ?>
    <div class="alert alert-warning">
        <strong>警告：</strong> AI 功能未配置。请前往系统设置配置 AI 功能后再使用此插件。
    </div>
    <?php endif; ?>
    
    <!-- 标签页导航 -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $active_tab == 'settings' ? 'active' : '' ?>" href="?plugin=ai_comment_reply&tab=settings">插件设置</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab == 'author' ? 'active' : '' ?>" href="?plugin=ai_comment_reply&tab=author">作者作品</a>
        </li>
    </ul>
    
    <?php if ($active_tab == 'settings'): ?>
    
    <form action="?plugin=ai_comment_reply&tab=settings" method="post" class="ai-settings-form" enctype="multipart/form-data">
        <div class="ai-settings-section">
            <h3>基本设置</h3>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">自动回复评论</label>
                <div class="col-sm-9 d-flex align-items-center">
                    <label class="ai-toggle-switch">
                        <input type="checkbox" name="auto_reply_enabled" <?= $settings['auto_reply_enabled'] ? 'checked' : '' ?>>
                        <span class="ai-toggle-slider"></span>
                    </label>
                    <span>启用 AI 自动回复评论</span>
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">忽略管理员评论</label>
                <div class="col-sm-9 d-flex align-items-center">
                    <label class="ai-toggle-switch">
                        <input type="checkbox" name="skip_admin_comments" <?= $settings['skip_admin_comments'] ? 'checked' : '' ?>>
                        <span class="ai-toggle-slider"></span>
                    </label>
                    <span>不对管理员发表的评论进行 AI 回复</span>
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">回复延迟（秒）</label>
                <div class="col-sm-9">
                    <input type="number" class="form-control" name="reply_delay" value="<?= $settings['reply_delay'] ?>" min="0" max="300">
                    <small class="form-text text-muted">设置 AI 回复的延迟时间（秒），0 表示立即回复，建议不要改动</small>
                </div>
            </div>
        </div>
        
        <div class="ai-settings-section">
            <h3>AI 账户设置</h3>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">AI 名称</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" name="ai_name" value="<?= $settings['ai_name'] ?>" required>
                    <small class="form-text text-muted">AI 回复评论时显示的名称</small>
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">AI 邮箱</label>
                <div class="col-sm-9">
                    <input type="email" class="form-control" name="ai_email" value="<?= $settings['ai_email'] ?>" required>
                    <small class="form-text text-muted">用于生成头像和标识 AI 回复</small>
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">AI 网址（可选）</label>
                <div class="col-sm-9">
                    <input type="url" class="form-control" name="ai_url" value="<?= $settings['ai_url'] ?>">
                    <small class="form-text text-muted">可选，AI 评论者的链接</small>
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">自定义头像</label>
                <div class="col-sm-9">
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="use_custom_avatar" name="use_custom_avatar" <?= isset($settings['use_custom_avatar']) && $settings['use_custom_avatar'] ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="use_custom_avatar">使用自定义头像</label>
                    </div>
                    
                    <div class="input-group mb-3">
                        <input type="file" class="form-control" name="avatar_file" accept="image/jpeg,image/png,image/gif">
                        <div class="input-group-append">
                            <span class="input-group-text">上传</span>
                        </div>
                    </div>
                    
                    <?php if (!empty($settings['custom_avatar'])): ?>
                    <div class="mb-2">
                        <img src="<?= $settings['custom_avatar'] ?>" class="ai-avatar-preview" alt="AI头像">
                        <small class="form-text text-muted">当前头像</small>
                    </div>
                    <?php endif; ?>
                    
                    <input type="text" class="form-control" name="custom_avatar" value="<?= isset($settings['custom_avatar']) ? $settings['custom_avatar'] : '' ?>" placeholder="或输入头像URL">
                    <small class="form-text text-muted">上传自定义头像或输入头像URL</small>
                </div>
            </div>
        </div>
        
        <div class="ai-settings-section">
            <h3>AI 响应设置</h3>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">系统提示语</label>
                <div class="col-sm-9">
                    <textarea class="form-control" name="system_prompt" rows="4"><?= $settings['system_prompt'] ?></textarea>
                    <small class="form-text text-muted">设置 AI 的基本行为指导，定义其角色和响应风格</small>
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">回复风格</label>
                <div class="col-sm-9">
                    <select class="form-control" name="response_style">
                        <option value="friendly" <?= $settings['response_style'] == 'friendly' ? 'selected' : '' ?>>友好</option>
                        <option value="professional" <?= $settings['response_style'] == 'professional' ? 'selected' : '' ?>>专业</option>
                        <option value="humorous" <?= $settings['response_style'] == 'humorous' ? 'selected' : '' ?>>幽默</option>
                        <option value="empathetic" <?= $settings['response_style'] == 'empathetic' ? 'selected' : '' ?>>共情</option>
                        <option value="concise" <?= $settings['response_style'] == 'concise' ? 'selected' : '' ?>>简洁</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">最大回复长度</label>
                <div class="col-sm-9">
                    <input type="number" class="form-control" name="max_length" value="<?= $settings['max_length'] ?>" min="50" max="1000">
                    <small class="form-text text-muted">AI 回复的最大字符数</small>
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">关键词过滤</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" name="keywords_to_skip" value="<?= $settings['keywords_to_skip'] ?>">
                    <small class="form-text text-muted">包含这些关键词的评论将被忽略（以逗号分隔）</small>
                </div>
            </div>
        </div>
        
        <div class="ai-settings-section">
            <h3>AI 测试</h3>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">测试提示语</label>
                <div class="col-sm-9">
                    <textarea class="form-control" name="test_prompt" rows="3" placeholder="输入一个测试评论，查看 AI 如何回复"><?= isset($_POST['test_prompt']) ? $_POST['test_prompt'] : '这篇文章写得很好，我有个问题想请教一下。' ?></textarea>
                </div>
            </div>
            
            <?php if (!empty($test_result)): ?>
            <div class="form-group row">
                <label class="col-sm-3 col-form-label">AI 回复结果</label>
                <div class="col-sm-9">
                    <div class="card">
                        <div class="card-body">
                            <?= nl2br(htmlspecialchars($test_result)) ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="form-group row">
                <div class="col-sm-9 offset-sm-3">
                    <button type="submit" name="test_ai" class="btn btn-info">测试 AI 回复</button>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <button type="submit" name="save_ai_settings" class="btn btn-primary">保存设置</button>
        </div>
    </form>
    
    <?php elseif ($active_tab == 'author'): ?>
    
    <div class="ai-settings-section">
        <h3>作者其他作品</h3>
        
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">感谢使用 AI 评论回复插件！</h5>
                        <p class="card-text">查看属余的其他优质 emlog 插件和主题，提升您的博客体验。</p>
                        <a href="https://www.emlog.net/author/index/858" target="_blank" class="btn btn-primary">访问我的作品页</a>
                    </div>
                </div>
                
                <div class="card-deck">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">小红书笔记插件</h5>
                            <p class="card-text">一款功能强大的仿小红书的微语插件。</p>
                        </div>
                        <div class="card-footer">
                            <a href="https://www.emlog.net/plugin/detail/910" target="_blank" class="btn btn-sm btn-outline-primary">查看详情</a>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">爱发电赞助插件</h5>
                            <p class="card-text">整合爱发电赞助功能，展示赞助用户信息，支持接收爱发电Webhook通知并处理赞助事件。</p>
                        </div>
                        <div class="card-footer">
                            <a href="https://www.emlog.net/plugin/detail/911" target="_blank" class="btn btn-sm btn-outline-primary">查看详情</a>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">时光邮局插件</h5>
                            <p class="card-text">允许用户向未来的自己发送邮件。</p>
                        </div>
                        <div class="card-footer">
                            <a href="https://www.emlog.net/plugin/detail/907" target="_blank" class="btn btn-sm btn-outline-primary">查看详情</a>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h5>更多作品推荐</h5>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            缤纷云存储插件
                            <a href="https://www.emlog.net/plugin/detail/908" target="_blank" class="btn btn-sm btn-outline-primary">查看</a>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            123云盘存储插件
                            <a href="https://www.emlog.net/plugin/detail/905" target="_blank" class="btn btn-sm btn-outline-primary">查看</a>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            简约积分商城插件
                            <a href="https://www.emlog.net/plugin/detail/899" target="_blank" class="btn btn-sm btn-outline-primary">查看</a>
                        </li>
                    </ul>
                </div>
                
                <div class="alert alert-info mt-4">
                    <p><strong>需要定制开发？</strong> 提供 emlog 主题与插件定制服务，为您的网站打造独特功能。</p>
                    <p>请通过邮件联系：<a href="mailto:mail@waikanl.cn">mail@waikanl.cn</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
    
    <script>
    // 预览上传的头像
    document.addEventListener('DOMContentLoaded', function() {
        const avatarFileInput = document.querySelector('input[name="avatar_file"]');
        if (avatarFileInput) {
            avatarFileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewContainer = document.querySelector('.ai-avatar-preview');
                        if (previewContainer) {
                            previewContainer.src = e.target.result;
                        } else {
                            const newPreview = document.createElement('img');
                            newPreview.className = 'ai-avatar-preview';
                            newPreview.src = e.target.result;
                            newPreview.alt = 'AI头像预览';
                            
                            const previewText = document.createElement('small');
                            previewText.className = 'form-text text-muted';
                            previewText.textContent = '头像预览';
                            
                            const container = document.createElement('div');
                            container.className = 'mb-2';
                            container.appendChild(newPreview);
                            container.appendChild(previewText);
                            
                            const avatarUrlInput = document.querySelector('input[name="custom_avatar"]');
                            avatarUrlInput.parentNode.insertBefore(container, avatarUrlInput);
                        }
                    }
                    reader.readAsDataURL(file);
                }
            });
        }
    });
    </script>
    <script>
            $("#menu_category_ext").addClass('active');
        </script>
    <?php
}