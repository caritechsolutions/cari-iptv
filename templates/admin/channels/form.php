<div class="page-header">
    <h1 class="page-title"><?= $channel ? 'Edit Channel' : 'Add Channel' ?></h1>
    <p class="page-subtitle">
        <?= $channel ? 'Update channel details, stream settings, and availability.' : 'Create a new live TV channel.' ?>
    </p>
</div>

<form action="<?= $channel ? "/admin/channels/{$channel['id']}/update" : '/admin/channels/store' ?>"
      method="POST" enctype="multipart/form-data" class="channel-form">
    <input type="hidden" name="_token" value="<?= $csrf ?>">

    <!-- Tabs Navigation -->
    <div class="form-tabs">
        <button type="button" class="tab-btn active" data-tab="metadata">
            <i class="lucide-file-text"></i> Metadata
        </button>
        <button type="button" class="tab-btn" data-tab="epg">
            <i class="lucide-calendar"></i> EPG
        </button>
        <button type="button" class="tab-btn" data-tab="availability">
            <i class="lucide-globe"></i> Availability
        </button>
    </div>

    <!-- Metadata Tab -->
    <div class="tab-content active" id="tab-metadata">
        <div class="grid grid-2 mb-3">
            <!-- Logo Upload Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Channel Logos</h3>
                </div>
                <div class="card-body">
                    <div class="logo-uploads">
                        <!-- Square Logo -->
                        <div class="logo-upload-group">
                            <label class="form-label">Logo</label>
                            <div class="logo-preview-container">
                                <div class="logo-preview" id="logoPreview">
                                    <?php if (!empty($channel['logo_url'])): ?>
                                        <img src="<?= htmlspecialchars($channel['logo_url']) ?>" alt="Logo">
                                    <?php else: ?>
                                        <i class="lucide-image"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="logo-actions">
                                    <label class="btn btn-primary btn-sm">
                                        <i class="lucide-upload"></i> Upload
                                        <input type="file" name="logo" accept="image/*" style="display: none;"
                                               onchange="previewLogo(this, 'logoPreview')">
                                    </label>
                                    <?php if (!empty($channel['logo_url'])): ?>
                                        <button type="button" class="btn btn-secondary btn-sm"
                                                onclick="removeLogo('logo')">Reset</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <small class="form-help">400 x 400 JPG, GIF, PNG</small>
                        </div>

                        <!-- Landscape Logo -->
                        <div class="logo-upload-group">
                            <label class="form-label">Landscape Logo</label>
                            <div class="logo-preview-container landscape">
                                <div class="logo-preview landscape" id="landscapePreview">
                                    <?php if (!empty($channel['logo_landscape_url'])): ?>
                                        <img src="<?= htmlspecialchars($channel['logo_landscape_url']) ?>" alt="Landscape Logo">
                                    <?php else: ?>
                                        <i class="lucide-image"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="logo-actions">
                                    <label class="btn btn-primary btn-sm">
                                        <i class="lucide-upload"></i> Upload
                                        <input type="file" name="logo_landscape" accept="image/*" style="display: none;"
                                               onchange="previewLogo(this, 'landscapePreview')">
                                    </label>
                                    <?php if (!empty($channel['logo_landscape_url'])): ?>
                                        <button type="button" class="btn btn-secondary btn-sm"
                                                onclick="removeLogo('landscape')">Reset</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <small class="form-help">500 x 296 JPG, GIF, PNG</small>
                        </div>
                    </div>

                    <!-- Publish Options -->
                    <div class="publish-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_published" value="1"
                                   <?= ($channel['is_published'] ?? false) ? 'checked' : '' ?>>
                            <span class="checkbox-text">
                                <strong>Published</strong>
                                <small>Channel is visible to users</small>
                            </span>
                        </label>

                        <label class="checkbox-label">
                            <input type="checkbox" name="available_without_purchase" value="1"
                                   <?= ($channel['available_without_purchase'] ?? false) ? 'checked' : '' ?>>
                            <span class="checkbox-text">
                                <strong>Available Without Purchase</strong>
                                <small>Free channel, no subscription required</small>
                            </span>
                        </label>

                        <label class="checkbox-label">
                            <input type="checkbox" name="show_to_demo_users" value="1"
                                   <?= ($channel['show_to_demo_users'] ?? true) ? 'checked' : '' ?>>
                            <span class="checkbox-text">
                                <strong>Show to Demo Users</strong>
                                <small>Visible in demo/trial mode</small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Basic Info Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Basic Information</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label" for="name">Title *</label>
                        <input type="text" id="name" name="name" class="form-input"
                               value="<?= htmlspecialchars($channel['name'] ?? '') ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="key_code">Key Code</label>
                            <input type="text" id="key_code" name="key_code" class="form-input"
                                   value="<?= htmlspecialchars($channel['key_code'] ?? '') ?>"
                                   placeholder="Auto-generated if empty">
                            <small class="form-help">Unique identifier for this channel</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="channel_number">Channel Number</label>
                            <input type="number" id="channel_number" name="channel_number" class="form-input"
                                   value="<?= htmlspecialchars($channel['channel_number'] ?? '') ?>" min="1">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="age_limit">Age Limit</label>
                            <select id="age_limit" name="age_limit" class="form-input">
                                <option value="0+" <?= ($channel['age_limit'] ?? '0+') === '0+' ? 'selected' : '' ?>>0+</option>
                                <option value="7+" <?= ($channel['age_limit'] ?? '') === '7+' ? 'selected' : '' ?>>7+</option>
                                <option value="12+" <?= ($channel['age_limit'] ?? '') === '12+' ? 'selected' : '' ?>>12+</option>
                                <option value="16+" <?= ($channel['age_limit'] ?? '') === '16+' ? 'selected' : '' ?>>16+</option>
                                <option value="18+" <?= ($channel['age_limit'] ?? '') === '18+' ? 'selected' : '' ?>>18+</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="sort_order">Sort Order</label>
                            <input type="number" id="sort_order" name="sort_order" class="form-input"
                                   value="<?= htmlspecialchars($channel['sort_order'] ?? 0) ?>" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">OS Platforms</label>
                        <div class="os-platforms">
                            <?php
                            $platforms = $channel['os_platforms'] ?? ['all'];
                            if (!is_array($platforms)) $platforms = ['all'];
                            ?>
                            <label class="platform-checkbox">
                                <input type="checkbox" name="os_platforms[]" value="all"
                                       <?= in_array('all', $platforms) ? 'checked' : '' ?>>
                                <span>All Platforms</span>
                            </label>
                            <label class="platform-checkbox">
                                <input type="checkbox" name="os_platforms[]" value="android"
                                       <?= in_array('android', $platforms) ? 'checked' : '' ?>>
                                <span>Android</span>
                            </label>
                            <label class="platform-checkbox">
                                <input type="checkbox" name="os_platforms[]" value="ios"
                                       <?= in_array('ios', $platforms) ? 'checked' : '' ?>>
                                <span>iOS</span>
                            </label>
                            <label class="platform-checkbox">
                                <input type="checkbox" name="os_platforms[]" value="web"
                                       <?= in_array('web', $platforms) ? 'checked' : '' ?>>
                                <span>Web</span>
                            </label>
                            <label class="platform-checkbox">
                                <input type="checkbox" name="os_platforms[]" value="smarttv"
                                       <?= in_array('smarttv', $platforms) ? 'checked' : '' ?>>
                                <span>Smart TV</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categories and Packages -->
        <div class="grid grid-2 mb-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Categories</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Select Categories</label>
                        <div class="multi-select-list">
                            <?php
                            $selectedCats = array_column($channel['categories'] ?? [], 'category_id');
                            $primaryCat = null;
                            foreach (($channel['categories'] ?? []) as $cat) {
                                if ($cat['is_primary']) $primaryCat = $cat['category_id'];
                            }
                            ?>
                            <?php foreach ($categories as $cat): ?>
                                <label class="multi-select-item">
                                    <input type="checkbox" name="categories[]" value="<?= $cat['id'] ?>"
                                           <?= in_array($cat['id'], $selectedCats) ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($cat['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($categories)): ?>
                                <p class="text-muted text-sm">No categories available. <a href="/admin/categories">Create one</a></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="primary_category">Primary Category</label>
                        <select id="primary_category" name="primary_category" class="form-input">
                            <option value="">Select primary category...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $primaryCat == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-help">The main category for this channel</small>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Packages</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Include in Packages</label>
                        <div class="multi-select-list">
                            <?php $selectedPkgs = array_column($channel['packages'] ?? [], 'package_id'); ?>
                            <?php foreach ($packages as $pkg): ?>
                                <label class="multi-select-item">
                                    <input type="checkbox" name="packages[]" value="<?= $pkg['id'] ?>"
                                           <?= in_array($pkg['id'], $selectedPkgs) ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($pkg['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($packages)): ?>
                                <p class="text-muted text-sm">No packages available. <a href="/admin/packages">Create one</a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stream Settings -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Stream Settings</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label" for="streaming_server_id">Streaming Server</label>
                        <select id="streaming_server_id" name="streaming_server_id" class="form-input">
                            <option value="">External URL (manual entry)</option>
                            <?php foreach ($servers as $srv): ?>
                                <option value="<?= $srv['id'] ?>"
                                        <?= ($channel['streaming_server_id'] ?? '') == $srv['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($srv['name']) ?> (<?= ucfirst($srv['type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="external_id">External ID</label>
                        <input type="text" id="external_id" name="external_id" class="form-input"
                               value="<?= htmlspecialchars($channel['external_id'] ?? '') ?>"
                               placeholder="ID from external system">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="stream_url">Stream URL *</label>
                    <input type="url" id="stream_url" name="stream_url" class="form-input"
                           value="<?= htmlspecialchars($channel['stream_url'] ?? '') ?>"
                           placeholder="https://stream.example.com/channel/index.m3u8" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="stream_url_backup">Backup Stream URL</label>
                    <input type="url" id="stream_url_backup" name="stream_url_backup" class="form-input"
                           value="<?= htmlspecialchars($channel['stream_url_backup'] ?? '') ?>"
                           placeholder="https://backup.example.com/channel/index.m3u8">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="checkbox-label inline">
                            <input type="checkbox" name="is_hd" value="1"
                                   <?= ($channel['is_hd'] ?? false) ? 'checked' : '' ?>>
                            <span>HD Channel</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label inline">
                            <input type="checkbox" name="is_4k" value="1"
                                   <?= ($channel['is_4k'] ?? false) ? 'checked' : '' ?>>
                            <span>4K Channel</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label inline">
                            <input type="checkbox" name="is_active" value="1"
                                   <?= ($channel['is_active'] ?? true) ? 'checked' : '' ?>>
                            <span>Active</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Catchup Settings -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Catchup / Time-Shift</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="catchup_days">Catchup Duration</label>
                        <input type="number" id="catchup_days" name="catchup_days" class="form-input"
                               value="<?= htmlspecialchars($channel['catchup_days'] ?? 0) ?>" min="0">
                        <small class="form-help">Set to 0 to disable catchup</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="catchup_period_type">Period</label>
                        <select id="catchup_period_type" name="catchup_period_type" class="form-input">
                            <option value="days" <?= ($channel['catchup_period_type'] ?? 'days') === 'days' ? 'selected' : '' ?>>Day(s)</option>
                            <option value="hours" <?= ($channel['catchup_period_type'] ?? '') === 'hours' ? 'selected' : '' ?>>Hour(s)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Owner -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Content Owner</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="content_owner_id">Content Owner</label>
                    <select id="content_owner_id" name="content_owner_id" class="form-input">
                        <option value="">No Content Owner</option>
                        <?php foreach ($contentOwners as $owner): ?>
                            <option value="<?= $owner['id'] ?>"
                                    <?= ($channel['content_owner_id'] ?? '') == $owner['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($owner['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- EPG Tab -->
    <div class="tab-content" id="tab-epg">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">EPG Settings</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="epg_channel_id">EPG Channel ID</label>
                    <input type="text" id="epg_channel_id" name="epg_channel_id" class="form-input"
                           value="<?= htmlspecialchars($channel['epg_channel_id'] ?? '') ?>"
                           placeholder="Channel ID from EPG source (XMLTV)">
                    <small class="form-help">This ID must match the channel ID in your EPG/XMLTV data</small>
                </div>

                <?php if (!empty($channel['epg_last_update'])): ?>
                    <div class="info-section">
                        <h4>EPG Status</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Last Update</span>
                                <span class="info-value"><?= date('M j, Y g:i a', strtotime($channel['epg_last_update'])) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="epg-notice">
                    <i class="lucide-info"></i>
                    <div>
                        <strong>EPG Data</strong>
                        <p>EPG data is imported separately via the EPG management section. Configure the EPG Channel ID above to link this channel with program guide data.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Availability Tab -->
    <div class="tab-content" id="tab-availability">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Geographic Settings</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="country">Country</label>
                        <select id="country" name="country" class="form-input">
                            <option value="">All Countries</option>
                            <option value="JM" <?= ($channel['country'] ?? '') === 'JM' ? 'selected' : '' ?>>Jamaica</option>
                            <option value="TT" <?= ($channel['country'] ?? '') === 'TT' ? 'selected' : '' ?>>Trinidad and Tobago</option>
                            <option value="BB" <?= ($channel['country'] ?? '') === 'BB' ? 'selected' : '' ?>>Barbados</option>
                            <option value="GY" <?= ($channel['country'] ?? '') === 'GY' ? 'selected' : '' ?>>Guyana</option>
                            <option value="BS" <?= ($channel['country'] ?? '') === 'BS' ? 'selected' : '' ?>>Bahamas</option>
                            <option value="LC" <?= ($channel['country'] ?? '') === 'LC' ? 'selected' : '' ?>>Saint Lucia</option>
                            <option value="AG" <?= ($channel['country'] ?? '') === 'AG' ? 'selected' : '' ?>>Antigua and Barbuda</option>
                            <option value="VC" <?= ($channel['country'] ?? '') === 'VC' ? 'selected' : '' ?>>Saint Vincent</option>
                            <option value="GD" <?= ($channel['country'] ?? '') === 'GD' ? 'selected' : '' ?>>Grenada</option>
                            <option value="DM" <?= ($channel['country'] ?? '') === 'DM' ? 'selected' : '' ?>>Dominica</option>
                            <option value="KN" <?= ($channel['country'] ?? '') === 'KN' ? 'selected' : '' ?>>Saint Kitts and Nevis</option>
                            <option value="US" <?= ($channel['country'] ?? '') === 'US' ? 'selected' : '' ?>>United States</option>
                            <option value="CA" <?= ($channel['country'] ?? '') === 'CA' ? 'selected' : '' ?>>Canada</option>
                            <option value="GB" <?= ($channel['country'] ?? '') === 'GB' ? 'selected' : '' ?>>United Kingdom</option>
                        </select>
                        <small class="form-help">Origin country of the channel</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="language">Language</label>
                        <select id="language" name="language" class="form-input">
                            <option value="">Not Specified</option>
                            <option value="en" <?= ($channel['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                            <option value="es" <?= ($channel['language'] ?? '') === 'es' ? 'selected' : '' ?>>Spanish</option>
                            <option value="fr" <?= ($channel['language'] ?? '') === 'fr' ? 'selected' : '' ?>>French</option>
                            <option value="pt" <?= ($channel['language'] ?? '') === 'pt' ? 'selected' : '' ?>>Portuguese</option>
                            <option value="hi" <?= ($channel['language'] ?? '') === 'hi' ? 'selected' : '' ?>>Hindi</option>
                            <option value="pa" <?= ($channel['language'] ?? '') === 'pa' ? 'selected' : '' ?>>Patois/Creole</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Availability Summary</h3>
            </div>
            <div class="card-body">
                <div class="availability-summary">
                    <div class="summary-item">
                        <i class="lucide-check-circle text-success"></i>
                        <span>Channel will be visible based on:</span>
                    </div>
                    <ul class="summary-list">
                        <li><strong>Published Status:</strong> Must be checked to appear</li>
                        <li><strong>Active Status:</strong> Must be active for streaming</li>
                        <li><strong>Package Assignment:</strong> User must have a package that includes this channel</li>
                        <li><strong>Available Without Purchase:</strong> If checked, visible to all users</li>
                        <li><strong>Demo Users:</strong> If checked, visible in demo/trial mode</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="form-actions">
        <a href="/admin/channels" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="lucide-save"></i>
            <?= $channel ? 'Update Channel' : 'Create Channel' ?>
        </button>
    </div>
</form>

<style>
.form-tabs {
    display: flex;
    gap: 0.25rem;
    background: var(--bg-card);
    padding: 0.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
}

.tab-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: transparent;
    border: none;
    border-radius: 8px;
    color: var(--text-secondary);
    font-size: 0.9375rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.tab-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.tab-btn.active {
    background: var(--primary);
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.logo-uploads {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.logo-upload-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.logo-preview-container {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.logo-preview {
    width: 100px;
    height: 100px;
    background: var(--bg-hover);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 2px dashed var(--border-color);
}

.logo-preview.landscape {
    width: 150px;
    height: 89px;
}

.logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.logo-preview i {
    font-size: 2rem;
    color: var(--text-muted);
}

.logo-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.publish-options {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--bg-hover);
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s;
}

.checkbox-label:hover {
    background: var(--border-color);
}

.checkbox-label.inline {
    display: inline-flex;
    padding: 0.5rem 0.75rem;
    margin-right: 1rem;
}

.checkbox-label input[type="checkbox"] {
    margin-top: 0.125rem;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.checkbox-text {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.checkbox-text strong {
    color: var(--text-primary);
    font-size: 0.9rem;
}

.checkbox-text small {
    color: var(--text-muted);
    font-size: 0.8rem;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.9375rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-help {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.os-platforms {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.platform-checkbox {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: var(--bg-hover);
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
}

.platform-checkbox:hover {
    background: var(--border-color);
}

.platform-checkbox input {
    cursor: pointer;
}

.multi-select-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.5rem;
}

.multi-select-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.875rem;
}

.multi-select-item:hover {
    background: var(--bg-hover);
}

.multi-select-item input {
    cursor: pointer;
}

.info-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

.info-section h4 {
    margin-bottom: 0.75rem;
    color: var(--text-secondary);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.info-grid {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
}

.info-label {
    color: var(--text-muted);
}

.info-value {
    color: var(--text-primary);
}

.epg-notice {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: rgba(59, 130, 246, 0.1);
    border-radius: 8px;
    margin-top: 1rem;
}

.epg-notice i {
    color: var(--info);
    flex-shrink: 0;
}

.epg-notice strong {
    display: block;
    margin-bottom: 0.25rem;
    color: var(--text-primary);
}

.epg-notice p {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 0;
}

.availability-summary {
    padding: 1rem;
    background: var(--bg-hover);
    border-radius: 8px;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.summary-list {
    margin: 0;
    padding-left: 2rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.summary-list li {
    margin-bottom: 0.5rem;
}

.summary-list strong {
    color: var(--text-primary);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding-top: 1rem;
}

@media (max-width: 768px) {
    .form-row,
    .grid-2,
    .logo-uploads {
        grid-template-columns: 1fr;
    }

    .form-tabs {
        flex-wrap: wrap;
    }

    .tab-btn {
        flex: 1 1 auto;
        justify-content: center;
    }
}
</style>

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tabId = this.dataset.tab;

        // Update buttons
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        // Update content
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('tab-' + tabId).classList.add('active');
    });
});

// Logo preview
function previewLogo(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Remove logo (for existing channels)
function removeLogo(type) {
    <?php if ($channel): ?>
    if (confirm('Are you sure you want to remove this logo?')) {
        fetch('/admin/channels/<?= $channel['id'] ?>/remove-logo?type=' + type)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
    }
    <?php endif; ?>
}

// Auto-update primary category dropdown based on selected categories
document.querySelectorAll('input[name="categories[]"]').forEach(cb => {
    cb.addEventListener('change', updatePrimaryOptions);
});

function updatePrimaryOptions() {
    const selected = [];
    document.querySelectorAll('input[name="categories[]"]:checked').forEach(cb => {
        selected.push(cb.value);
    });

    const primarySelect = document.getElementById('primary_category');
    const currentValue = primarySelect.value;

    // Enable/disable options based on selection
    Array.from(primarySelect.options).forEach(opt => {
        if (opt.value && !selected.includes(opt.value)) {
            opt.disabled = true;
            if (opt.value === currentValue) {
                primarySelect.value = '';
            }
        } else {
            opt.disabled = false;
        }
    });
}

// Run on page load
updatePrimaryOptions();
</script>
