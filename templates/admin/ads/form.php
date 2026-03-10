<?php
$pageTitle = $campaign ? 'Edit Campaign' : 'Create Campaign';
$isEdit = !empty($campaign);
?>

<style>
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
    .form-full { grid-column: 1 / -1; }
    .section-title { font-size: 1.1rem; font-weight: 600; margin: 1.5rem 0 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
    .section-title:first-child { margin-top: 0; }
    .creative-card { background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem; }
    .creative-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
    .creative-type-label { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 500; }
    .placement-card { background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem; }
    .targeting-row { display: flex; gap: 0.5rem; align-items: flex-start; margin-bottom: 0.5rem; flex-wrap: wrap; }
    .targeting-row select, .targeting-row input { font-size: 0.8rem; padding: 0.375rem 0.5rem; }
    .rule-multi-dropdown.show { display: block !important; }
    .rule-multi-toggle { user-select: none; }
    .rule-value-container { position: relative; }
    .tab-nav { display: flex; border-bottom: 2px solid var(--border-color); margin-bottom: 1rem; gap: 0; }
    .tab-btn { padding: 0.75rem 1.25rem; background: none; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; cursor: pointer; color: var(--text-muted); font-size: 0.875rem; font-weight: 500; transition: all 0.2s; }
    .tab-btn:hover { color: var(--text-primary); }
    .tab-btn.active { color: var(--primary-light); border-bottom-color: var(--primary); }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .type-fields { display: none; }
    .type-fields.active { display: block; }
    .help-text { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }
    .no-items { text-align: center; padding: 2rem; color: var(--text-muted); }

    /* Modal */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: #1e293b; border: 1px solid #475569; border-radius: 12px; width: 90%; max-width: 640px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); }
    .modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #1e293b; }
    .modal-header h3 { font-size: 1rem; font-weight: 600; }
    .modal-body { padding: 1.25rem; background: #1e293b; }
    .modal-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.5rem; background: #1e293b; }
    .modal-close { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.25rem; padding: 0.25rem; }
</style>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title"><?= $isEdit ? 'Edit Campaign' : 'Create Campaign' ?></h1>
        <p class="page-subtitle"><?= $isEdit ? 'Update campaign settings, ads, and placements.' : 'Set up a new advertising campaign.' ?></p>
    </div>
    <div>
        <a href="/admin/ads" class="btn btn-secondary">Back to Campaigns</a>
    </div>
</div>

<!-- Tabs (only show all tabs in edit mode) -->
<?php if ($isEdit): ?>
<div class="tab-nav">
    <button class="tab-btn active" onclick="switchTab('details')">Campaign Details</button>
    <button class="tab-btn" onclick="switchTab('creatives')">Ads (<?= count($campaign['creatives'] ?? []) ?>)</button>
    <button class="tab-btn" onclick="switchTab('placements')">Placements (<?= count($campaign['placements'] ?? []) ?>)</button>
</div>
<?php endif; ?>

<!-- Campaign Details Tab -->
<div class="tab-content active" id="tab-details">
    <form method="POST" action="<?= $isEdit ? '/admin/ads/' . $campaign['id'] . '/update' : '/admin/ads/store' ?>">
        <input type="hidden" name="_token" value="<?= $csrf ?>">

        <div class="card">
            <div class="card-body">
                <h3 class="section-title" style="margin-top:0;">Basic Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Campaign Name *</label>
                        <input type="text" name="name" class="form-input" required
                               value="<?= htmlspecialchars($campaign['name'] ?? '') ?>"
                               placeholder="e.g. Holiday Sale Banner">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Advertiser</label>
                        <input type="text" name="advertiser" class="form-input"
                               value="<?= htmlspecialchars($campaign['advertiser'] ?? '') ?>"
                               placeholder="e.g. Digicel, Flow">
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-input" rows="2" placeholder="Campaign notes..."><?= htmlspecialchars($campaign['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-input">
                            <?php foreach (['draft', 'active', 'paused', 'completed', 'archived'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($campaign['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority (1=highest, 10=lowest)</label>
                        <input type="number" name="priority" class="form-input" min="1" max="10"
                               value="<?= $campaign['priority'] ?? 5 ?>">
                    </div>
                </div>

                <h3 class="section-title">Schedule</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="datetime-local" name="start_date" class="form-input"
                               value="<?= $campaign['start_date'] ? date('Y-m-d\TH:i', strtotime($campaign['start_date'])) : '' ?>">
                        <div class="help-text">Leave empty for immediate start</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="datetime-local" name="end_date" class="form-input"
                               value="<?= $campaign['end_date'] ? date('Y-m-d\TH:i', strtotime($campaign['end_date'])) : '' ?>">
                        <div class="help-text">Leave empty for no end date</div>
                    </div>
                </div>

                <h3 class="section-title">Budget & Caps</h3>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Daily Budget ($)</label>
                        <input type="number" name="daily_budget" class="form-input" step="0.01" min="0"
                               value="<?= $campaign['daily_budget'] ?? '' ?>" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Budget ($)</label>
                        <input type="number" name="total_budget" class="form-input" step="0.01" min="0"
                               value="<?= $campaign['total_budget'] ?? '' ?>" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Frequency Cap (per user/day)</label>
                        <input type="number" name="frequency_cap" class="form-input" min="0"
                               value="<?= $campaign['frequency_cap'] ?? '' ?>" placeholder="e.g. 3">
                        <div class="help-text">Max times a user sees this ad per day</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Daily Impressions Cap</label>
                        <input type="number" name="daily_impressions_cap" class="form-input" min="0"
                               value="<?= $campaign['daily_impressions_cap'] ?? '' ?>" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Impressions Cap</label>
                        <input type="number" name="total_impressions_cap" class="form-input" min="0"
                               value="<?= $campaign['total_impressions_cap'] ?? '' ?>" placeholder="Optional">
                    </div>
                </div>

                <?php if ($isEdit): ?>
                <h3 class="section-title">Performance</h3>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Total Impressions</label>
                        <div style="font-size:1.25rem;font-weight:600;"><?= number_format($campaign['total_impressions']) ?></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Clicks</label>
                        <div style="font-size:1.25rem;font-weight:600;"><?= number_format($campaign['total_clicks']) ?></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">CTR</label>
                        <div style="font-size:1.25rem;font-weight:600;">
                            <?= $campaign['total_impressions'] > 0 ? round(($campaign['total_clicks'] / $campaign['total_impressions']) * 100, 2) : 0 ?>%
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Campaign' : 'Create Campaign' ?></button>
                <a href="/admin/ads" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php if ($isEdit): ?>
<!-- Creatives Tab -->
<div class="tab-content" id="tab-creatives">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Ads</div>
            <button class="btn btn-primary btn-sm" onclick="openCreativeModal()">
                <i class="lucide-plus"></i> Add Ad
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($campaign['creatives'])): ?>
                <div class="no-items">
                    <i class="lucide-image" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                    No ads yet. Add your first ad.
                </div>
            <?php else: ?>
                <?php foreach ($campaign['creatives'] as $creative): ?>
                    <div class="creative-card">
                        <div class="creative-card-header">
                            <div>
                                <strong><?= htmlspecialchars($creative['name']) ?></strong>
                                <?php
                                $typeInfo = $adTypes[$creative['type']] ?? null;
                                $typeColor = $typeInfo['color'] ?? 'info';
                                ?>
                                <span class="creative-type-label" style="background:rgba(var(--<?= $typeColor ?>-rgb, 100,100,100),0.15);color:var(--<?= $typeColor ?>);">
                                    <?= $typeInfo['name'] ?? $creative['type'] ?>
                                </span>
                                <span class="badge badge-<?= $creative['status'] === 'active' ? 'success' : ($creative['status'] === 'paused' ? 'warning' : 'info') ?>">
                                    <?= ucfirst($creative['status']) ?>
                                </span>
                            </div>
                            <div style="display:flex;gap:0.25rem;">
                                <button class="btn btn-secondary btn-sm" onclick="editCreative(<?= htmlspecialchars(json_encode($creative)) ?>)">Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteCreative(<?= $creative['id'] ?>)">Delete</button>
                            </div>
                        </div>
                        <div style="font-size:0.8rem;color:var(--text-muted);">
                            <?php if ($creative['type'] === 'text_scroller' && $creative['scroll_text']): ?>
                                Text: "<?= htmlspecialchars(substr($creative['scroll_text'], 0, 100)) ?>..."
                            <?php elseif ($creative['type'] === 'banner' && $creative['image_url']): ?>
                                Image: <?= htmlspecialchars(basename($creative['image_url'])) ?> (<?= $creative['image_width'] ?>x<?= $creative['image_height'] ?>)
                            <?php elseif (in_array($creative['type'], ['pre_roll', 'mid_roll'])): ?>
                                <?php if ($creative['video_url']): ?>Video: <?= htmlspecialchars(basename($creative['video_url'])) ?><?php endif; ?>
                                <?php if ($creative['vast_tag_url']): ?>VAST Tag configured<?php endif; ?>
                                <?php if ($creative['video_duration']): ?> | <?= $creative['video_duration'] ?>s<?php endif; ?>
                                <?php if ($creative['skip_after']): ?> | Skip after <?= $creative['skip_after'] ?>s<?php endif; ?>
                            <?php endif; ?>
                            | Weight: <?= $creative['weight'] ?> | Impressions: <?= number_format($creative['impressions']) ?> | Clicks: <?= number_format($creative['clicks']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Placements Tab -->
<div class="tab-content" id="tab-placements">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Placements & Targeting</div>
            <button class="btn btn-primary btn-sm" onclick="openPlacementModal()">
                <i class="lucide-plus"></i> Add Placement
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($campaign['placements'])): ?>
                <div class="no-items">
                    <i class="lucide-target" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                    No placements yet. Add a placement to link ads to zones with targeting.
                </div>
            <?php else: ?>
                <?php foreach ($campaign['placements'] as $placement): ?>
                    <div class="placement-card">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                            <div>
                                <strong><?= htmlspecialchars($placement['creative_name']) ?></strong>
                                <i class="lucide-arrow-right" style="margin:0 0.25rem;font-size:0.75rem;color:var(--text-muted);"></i>
                                <strong><?= htmlspecialchars($placement['zone_name']) ?></strong>
                                <span class="badge badge-<?= $placement['status'] === 'active' ? 'success' : 'warning' ?>" style="margin-left:0.5rem;">
                                    <?= ucfirst($placement['status']) ?>
                                </span>
                            </div>
                            <div style="display:flex;gap:0.25rem;">
                                <button class="btn btn-secondary btn-sm" onclick="editPlacement(<?= htmlspecialchars(json_encode($placement)) ?>)">Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deletePlacement(<?= $placement['id'] ?>)">Delete</button>
                            </div>
                        </div>
                        <?php if (!empty($placement['targeting_rules'])): ?>
                            <div style="font-size:0.8rem;color:var(--text-muted);margin-top:0.5rem;">
                                <strong>Targeting:</strong>
                                <?php foreach ($placement['targeting_rules'] as $rule): ?>
                                    <span style="margin-left:0.5rem;">
                                        <?= ucfirst($rule['rule_type']) ?>:
                                        <em><?= $rule['rule_operator'] ?> [<?= is_array($rule['rule_value']) ? implode(', ', $rule['rule_value']) : $rule['rule_value'] ?>]</em>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size:0.8rem;color:var(--text-muted);margin-top:0.25rem;">
                                No targeting rules (shows to all users)
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Creative Modal -->
<div class="modal-overlay" id="creativeModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="creativeModalTitle">Add Ad</h3>
            <button class="modal-close" onclick="closeModal('creativeModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="creativeId" value="">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Name *</label>
                    <input type="text" id="creativeName" class="form-input" placeholder="e.g. Main Banner 728x90">
                </div>
                <div class="form-group">
                    <label class="form-label">Type *</label>
                    <select id="creativeType" class="form-input" onchange="showTypeFields(this.value)">
                        <option value="">Select type...</option>
                        <?php foreach ($adTypes as $key => $type): ?>
                            <option value="<?= $key ?>"><?= $type['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="creativeStatus" class="form-input">
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="paused">Paused</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Weight</label>
                    <input type="number" id="creativeWeight" class="form-input" value="100" min="1" max="1000">
                    <div class="help-text">Higher weight = more likely to serve</div>
                </div>
            </div>

            <!-- Text Scroller Fields -->
            <div class="type-fields" id="fields-text_scroller">
                <h4 style="margin:1rem 0 0.5rem;font-size:0.9rem;">Text Scroller Settings</h4>
                <div class="form-group">
                    <label class="form-label">Scroll Text *</label>
                    <textarea id="scrollText" class="form-input" rows="2" placeholder="Enter scrolling text..."></textarea>
                </div>
                <div class="form-group" style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:8px;padding:0.75rem;">
                    <label class="form-label" style="color:var(--primary-light);"><i class="lucide-sparkles"></i> Generate with AI</label>
                    <div style="display:flex;gap:0.5rem;">
                        <input type="text" id="aiTextPrompt" class="form-input" placeholder="e.g. Holiday sale 50% off streaming packages" style="flex:1;">
                        <button type="button" class="btn btn-primary btn-sm" onclick="generateAiText()" id="aiTextBtn">Generate</button>
                    </div>
                    <div class="help-text">Describe what the ad should say and AI will write it</div>
                </div>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Speed</label>
                        <select id="scrollSpeed" class="form-input">
                            <option value="slow">Slow</option>
                            <option value="normal" selected>Normal</option>
                            <option value="fast">Fast</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Text Color</label>
                        <input type="color" id="textColor" class="form-input" value="#FFFFFF" style="height:38px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Background Color</label>
                        <input type="color" id="bgColor" class="form-input" value="#000000" style="height:38px;">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Background Opacity (0-1)</label>
                    <input type="number" id="bgOpacity" class="form-input" value="0.80" min="0" max="1" step="0.05">
                </div>
            </div>

            <!-- Banner Fields -->
            <div class="type-fields" id="fields-banner">
                <h4 style="margin:1rem 0 0.5rem;font-size:0.9rem;">Banner Settings</h4>

                <!-- Image Source -->
                <div class="form-group">
                    <label class="form-label">Banner Image *</label>
                    <div id="bannerPreview" style="display:none;margin-bottom:0.75rem;border-radius:8px;overflow:hidden;border:1px solid var(--border-color);max-width:100%;">
                        <img id="bannerPreviewImg" src="" style="max-width:100%;max-height:200px;display:block;">
                    </div>
                    <div style="display:flex;gap:0.5rem;margin-bottom:0.5rem;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('bannerFileInput').click()">
                            <i class="lucide-upload"></i> Upload Image
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleBannerUrlInput()">
                            <i class="lucide-link"></i> Use URL
                        </button>
                    </div>
                    <input type="file" id="bannerFileInput" accept="image/*" style="display:none;" onchange="uploadBannerImage(this)">
                    <input type="url" id="bannerImageUrl" class="form-input" placeholder="https://... or upload above" style="display:none;">
                    <input type="hidden" id="bannerImageUrlHidden" value="">
                    <div id="bannerUploadStatus" class="help-text"></div>
                </div>

                <!-- AI Image Generation -->
                <div class="form-group" style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:8px;padding:0.75rem;">
                    <label class="form-label" style="color:var(--primary-light);"><i class="lucide-sparkles"></i> Generate Image with AI (DALL-E 3)</label>
                    <div style="display:flex;gap:0.5rem;margin-bottom:0.5rem;">
                        <input type="text" id="aiImagePrompt" class="form-input" placeholder="e.g. Tropical beach streaming advertisement banner, vibrant colors" style="flex:1;">
                        <button type="button" class="btn btn-primary btn-sm" onclick="generateAiImage()" id="aiImageBtn">Generate</button>
                    </div>
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <label class="form-label" style="margin:0;font-size:0.75rem;white-space:nowrap;">Size:</label>
                        <select id="aiImageSize" class="form-input" style="font-size:0.75rem;padding:0.25rem 0.5rem;width:auto;">
                            <option value="1792x1024">Landscape (1792x1024)</option>
                            <option value="1024x1024">Square (1024x1024)</option>
                            <option value="1024x1792">Portrait (1024x1792)</option>
                        </select>
                    </div>
                    <div class="help-text">Uses OpenAI DALL-E 3. Requires OpenAI API key in Settings > AI.</div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Width (px)</label>
                        <input type="number" id="imageWidth" class="form-input" placeholder="728">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Height (px)</label>
                        <input type="number" id="imageHeight" class="form-input" placeholder="90">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <select id="bannerPosition" class="form-input">
                            <option value="bottom">Bottom</option>
                            <option value="top">Top</option>
                            <option value="overlay_bottom">Overlay Bottom</option>
                            <option value="overlay_top">Overlay Top</option>
                            <option value="sidebar">Sidebar</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Click URL</label>
                        <input type="url" id="bannerClickUrl" class="form-input" placeholder="https://advertiser.com/landing">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Click Target</label>
                        <select id="clickTarget" class="form-input">
                            <option value="_blank">New Tab</option>
                            <option value="_self">Same Window</option>
                            <option value="deeplink">Deep Link</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Alt Text</label>
                    <input type="text" id="bannerAltText" class="form-input" placeholder="Ad description for accessibility">
                </div>
            </div>

            <!-- Pre-Roll / Mid-Roll Video Fields -->
            <div class="type-fields" id="fields-pre_roll">
                <h4 style="margin:1rem 0 0.5rem;font-size:0.9rem;">Video Ad Settings</h4>
                <div class="form-group">
                    <label class="form-label">Video Source</label>
                    <div style="display:flex;gap:0.5rem;margin-bottom:0.5rem;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('prerollFileInput').click()">
                            <i class="lucide-upload"></i> Upload Video
                        </button>
                        <span id="prerollUploadStatus" class="help-text" style="line-height:2;"></span>
                    </div>
                    <input type="file" id="prerollFileInput" accept="video/mp4,video/webm,video/ogg" style="display:none;" onchange="uploadVideoFile(this, 'videoUrl', 'prerollUploadStatus')">
                    <input type="url" id="videoUrl" class="form-input" placeholder="https://cdn.example.com/ad.mp4">
                    <div class="help-text">Upload a video or enter a direct MP4 URL</div>
                </div>
                <div class="form-group">
                    <label class="form-label">VAST Tag URL</label>
                    <input type="url" id="vastTagUrl" class="form-input" placeholder="https://adserver.example.com/vast?...">
                    <div class="help-text">Third-party VAST XML endpoint (takes priority over video URL)</div>
                </div>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Duration (seconds)</label>
                        <input type="number" id="videoDuration" class="form-input" placeholder="30">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Skip After (seconds)</label>
                        <input type="number" id="skipAfter" class="form-input" placeholder="5">
                        <div class="help-text">Empty = no skip</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Click URL</label>
                        <input type="url" id="videoClickUrl" class="form-input" placeholder="https://...">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Companion Banner URL</label>
                    <input type="url" id="companionBannerUrl" class="form-input" placeholder="https://cdn.example.com/companion.jpg">
                    <div class="help-text">Banner displayed alongside video ad</div>
                </div>
            </div>

            <!-- Mid-Roll specific fields -->
            <div class="type-fields" id="fields-mid_roll">
                <h4 style="margin:1rem 0 0.5rem;font-size:0.9rem;">Video Ad Settings</h4>
                <div class="form-group">
                    <label class="form-label">Video Source</label>
                    <div style="display:flex;gap:0.5rem;margin-bottom:0.5rem;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('midrollFileInput').click()">
                            <i class="lucide-upload"></i> Upload Video
                        </button>
                        <span id="midrollUploadStatus" class="help-text" style="line-height:2;"></span>
                    </div>
                    <input type="file" id="midrollFileInput" accept="video/mp4,video/webm,video/ogg" style="display:none;" onchange="uploadVideoFile(this, 'midVideoUrl', 'midrollUploadStatus')">
                    <input type="url" id="midVideoUrl" class="form-input" placeholder="https://cdn.example.com/ad.mp4">
                    <div class="help-text">Upload a video or enter a direct MP4 URL</div>
                </div>
                <div class="form-group">
                    <label class="form-label">VAST Tag URL</label>
                    <input type="url" id="midVastTagUrl" class="form-input" placeholder="https://adserver.example.com/vast?...">
                </div>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Duration (seconds)</label>
                        <input type="number" id="midVideoDuration" class="form-input" placeholder="30">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Skip After (seconds)</label>
                        <input type="number" id="midSkipAfter" class="form-input" placeholder="5">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Click URL</label>
                        <input type="url" id="midClickUrl" class="form-input" placeholder="https://...">
                    </div>
                </div>
                <h4 style="margin:1rem 0 0.5rem;font-size:0.9rem;">Mid-Roll Insertion Point</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Offset Type</label>
                        <select id="midrollOffsetType" class="form-input">
                            <option value="percent">Percentage of content</option>
                            <option value="seconds">Seconds into content</option>
                            <option value="cue">SCTE-35 cue point</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Offset Value</label>
                        <input type="text" id="midrollOffsetValue" class="form-input" placeholder="e.g. 50 (for 50%)">
                        <div class="help-text">For percent: 0-100. For seconds: number of seconds.</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Companion Banner URL</label>
                    <input type="url" id="midCompanionUrl" class="form-input" placeholder="https://...">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('creativeModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveCreative()">Save Ad</button>
        </div>
    </div>
</div>

<!-- Placement Modal -->
<div class="modal-overlay" id="placementModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="placementModalTitle">Add Placement</h3>
            <button class="modal-close" onclick="closeModal('placementModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="placementId" value="">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Ad *</label>
                    <select id="placementCreative" class="form-input">
                        <option value="">Select ad...</option>
                        <?php foreach ($campaign['creatives'] as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= $adTypes[$c['type']]['name'] ?? $c['type'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Zone *</label>
                    <select id="placementZone" class="form-input">
                        <option value="">Select zone...</option>
                        <?php foreach ($zones as $z): ?>
                            <option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['name']) ?> (<?= $adTypes[$z['zone_type']]['name'] ?? $z['zone_type'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="placementStatus" class="form-input">
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority (1=highest)</label>
                    <input type="number" id="placementPriority" class="form-input" value="5" min="1" max="10">
                </div>
            </div>

            <h4 style="margin:1rem 0 0.5rem;font-size:0.9rem;">Targeting Rules</h4>
            <div class="help-text" style="margin-bottom:0.75rem;">Define who sees this ad. Leave empty to show to everyone.</div>

            <div id="targetingRulesContainer"></div>

            <button type="button" class="btn btn-secondary btn-sm" onclick="addTargetingRule()" style="margin-top:0.5rem;">
                <i class="lucide-plus"></i> Add Targeting Rule
            </button>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('placementModal')">Cancel</button>
            <button class="btn btn-primary" onclick="savePlacement()">Save Placement</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const csrf = '<?= $csrf ?>';
const campaignId = <?= $campaign['id'] ?? 'null' ?>;
const channels = <?= json_encode($channels ?? []) ?>;
const categories = <?= json_encode($categories ?? []) ?>;
const packages = <?= json_encode($packages ?? []) ?>;

function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

function showTypeFields(type) {
    document.querySelectorAll('.type-fields').forEach(f => f.classList.remove('active'));
    if (type) {
        const el = document.getElementById('fields-' + type);
        if (el) el.classList.add('active');
    }
}

function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

// Creative Modal
function openCreativeModal() {
    document.getElementById('creativeId').value = '';
    document.getElementById('creativeName').value = '';
    document.getElementById('creativeType').value = '';
    document.getElementById('creativeStatus').value = 'draft';
    document.getElementById('creativeWeight').value = '100';
    document.querySelectorAll('.type-fields').forEach(f => f.classList.remove('active'));
    // Reset banner preview
    document.getElementById('bannerPreview').style.display = 'none';
    document.getElementById('bannerImageUrlHidden').value = '';
    document.getElementById('bannerImageUrl').value = '';
    document.getElementById('bannerImageUrl').style.display = 'none';
    document.getElementById('bannerUploadStatus').textContent = '';
    document.getElementById('creativeModalTitle').textContent = 'Add Ad';
    openModal('creativeModal');
}

function editCreative(creative) {
    document.getElementById('creativeId').value = creative.id;
    document.getElementById('creativeName').value = creative.name;
    document.getElementById('creativeType').value = creative.type;
    document.getElementById('creativeStatus').value = creative.status;
    document.getElementById('creativeWeight').value = creative.weight;
    showTypeFields(creative.type);

    // Populate type-specific fields
    if (creative.type === 'text_scroller') {
        document.getElementById('scrollText').value = creative.scroll_text || '';
        document.getElementById('scrollSpeed').value = creative.scroll_speed || 'normal';
        document.getElementById('textColor').value = creative.text_color || '#FFFFFF';
        document.getElementById('bgColor').value = creative.bg_color || '#000000';
        document.getElementById('bgOpacity').value = creative.bg_opacity || '0.80';
    } else if (creative.type === 'banner') {
        document.getElementById('bannerImageUrl').value = creative.image_url || '';
        document.getElementById('bannerImageUrlHidden').value = creative.image_url || '';
        document.getElementById('imageWidth').value = creative.image_width || '';
        document.getElementById('imageHeight').value = creative.image_height || '';
        document.getElementById('bannerPosition').value = creative.banner_position || 'bottom';
        document.getElementById('bannerClickUrl').value = creative.click_url || '';
        document.getElementById('clickTarget').value = creative.click_target || '_blank';
        document.getElementById('bannerAltText').value = creative.alt_text || '';
        if (creative.image_url) {
            document.getElementById('bannerPreviewImg').src = creative.image_url;
            document.getElementById('bannerPreview').style.display = 'block';
        }
    } else if (creative.type === 'pre_roll') {
        document.getElementById('videoUrl').value = creative.video_url || '';
        document.getElementById('vastTagUrl').value = creative.vast_tag_url || '';
        document.getElementById('videoDuration').value = creative.video_duration || '';
        document.getElementById('skipAfter').value = creative.skip_after || '';
        document.getElementById('videoClickUrl').value = creative.click_url || '';
        document.getElementById('companionBannerUrl').value = creative.companion_banner_url || '';
    } else if (creative.type === 'mid_roll') {
        document.getElementById('midVideoUrl').value = creative.video_url || '';
        document.getElementById('midVastTagUrl').value = creative.vast_tag_url || '';
        document.getElementById('midVideoDuration').value = creative.video_duration || '';
        document.getElementById('midSkipAfter').value = creative.skip_after || '';
        document.getElementById('midClickUrl').value = creative.click_url || '';
        document.getElementById('midrollOffsetType').value = creative.midroll_offset_type || 'percent';
        document.getElementById('midrollOffsetValue').value = creative.midroll_offset_value || '';
        document.getElementById('midCompanionUrl').value = creative.companion_banner_url || '';
    }

    document.getElementById('creativeModalTitle').textContent = 'Edit Ad';
    openModal('creativeModal');
}

// AI Text Generation
function generateAiText() {
    const prompt = document.getElementById('aiTextPrompt').value.trim();
    if (!prompt) { alert('Enter a prompt first'); return; }

    const btn = document.getElementById('aiTextBtn');
    const type = document.getElementById('creativeType').value;
    btn.disabled = true;
    btn.textContent = 'Generating...';

    const data = new URLSearchParams();
    data.append('_token', csrf);
    data.append('prompt', prompt);
    data.append('ad_type', type);

    fetch('/admin/ads/ai/generate-text', { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                document.getElementById('scrollText').value = d.text;
            } else {
                alert(d.message || 'AI generation failed');
            }
        })
        .catch(() => alert('Network error'))
        .finally(() => { btn.disabled = false; btn.textContent = 'Generate'; });
}

// AI Image Generation (DALL-E 3)
function generateAiImage() {
    const prompt = document.getElementById('aiImagePrompt').value.trim();
    if (!prompt) { alert('Enter an image description first'); return; }

    const btn = document.getElementById('aiImageBtn');
    const size = document.getElementById('aiImageSize').value;
    btn.disabled = true;
    btn.textContent = 'Generating...';

    const data = new URLSearchParams();
    data.append('_token', csrf);
    data.append('prompt', prompt);
    data.append('size', size);
    data.append('campaign_id', campaignId || '');

    fetch('/admin/ads/ai/generate-image', { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                setBannerImage(d.image_url, d.revised_prompt);
            } else {
                alert(d.message || 'Image generation failed');
            }
        })
        .catch(() => alert('Network error'))
        .finally(() => { btn.disabled = false; btn.textContent = 'Generate'; });
}

// Banner Image Upload
function uploadBannerImage(input) {
    if (!input.files || !input.files[0]) return;

    const status = document.getElementById('bannerUploadStatus');
    status.textContent = 'Uploading...';
    status.style.color = 'var(--primary-light)';

    const formData = new FormData();
    formData.append('_token', csrf);
    formData.append('image', input.files[0]);
    formData.append('campaign_id', campaignId || '');

    fetch('/admin/ads/upload/image', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                setBannerImage(d.image_url);
                if (d.width) document.getElementById('imageWidth').value = d.width;
                if (d.height) document.getElementById('imageHeight').value = d.height;
                status.textContent = 'Uploaded and converted to WebP';
                status.style.color = 'var(--success)';
            } else {
                status.textContent = d.message || 'Upload failed';
                status.style.color = 'var(--danger)';
            }
        })
        .catch(() => { status.textContent = 'Network error'; status.style.color = 'var(--danger)'; });

    input.value = '';
}

function setBannerImage(url, altText) {
    document.getElementById('bannerImageUrlHidden').value = url;
    document.getElementById('bannerImageUrl').value = url;
    const preview = document.getElementById('bannerPreview');
    const img = document.getElementById('bannerPreviewImg');
    img.src = url;
    preview.style.display = 'block';
    if (altText) document.getElementById('bannerAltText').value = altText;
}

function toggleBannerUrlInput() {
    const urlInput = document.getElementById('bannerImageUrl');
    urlInput.style.display = urlInput.style.display === 'none' ? 'block' : 'none';
}

// Video Upload
function uploadVideoFile(input, targetFieldId, statusId) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    const status = document.getElementById(statusId);

    if (file.size > 100 * 1024 * 1024) {
        status.textContent = 'File too large (max 100MB)';
        status.style.color = 'var(--danger)';
        return;
    }

    status.textContent = 'Uploading ' + (file.size / (1024*1024)).toFixed(1) + 'MB...';
    status.style.color = 'var(--primary-light)';

    const formData = new FormData();
    formData.append('_token', csrf);
    formData.append('video', file);
    formData.append('campaign_id', campaignId || '');

    fetch('/admin/ads/upload/video', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                document.getElementById(targetFieldId).value = d.video_url;
                status.textContent = 'Uploaded: ' + d.filename;
                status.style.color = 'var(--success)';
            } else {
                status.textContent = d.message || 'Upload failed';
                status.style.color = 'var(--danger)';
            }
        })
        .catch(() => { status.textContent = 'Network error'; status.style.color = 'var(--danger)'; });

    input.value = '';
}

function saveCreative() {
    const id = document.getElementById('creativeId').value;
    const type = document.getElementById('creativeType').value;

    const data = new URLSearchParams();
    data.append('_token', csrf);
    data.append('name', document.getElementById('creativeName').value);
    data.append('type', type);
    data.append('status', document.getElementById('creativeStatus').value);
    data.append('weight', document.getElementById('creativeWeight').value);

    // Type-specific data
    if (type === 'text_scroller') {
        data.append('scroll_text', document.getElementById('scrollText').value);
        data.append('scroll_speed', document.getElementById('scrollSpeed').value);
        data.append('text_color', document.getElementById('textColor').value);
        data.append('bg_color', document.getElementById('bgColor').value);
        data.append('bg_opacity', document.getElementById('bgOpacity').value);
    } else if (type === 'banner') {
        data.append('image_url', document.getElementById('bannerImageUrlHidden').value || document.getElementById('bannerImageUrl').value);
        data.append('image_width', document.getElementById('imageWidth').value);
        data.append('image_height', document.getElementById('imageHeight').value);
        data.append('banner_position', document.getElementById('bannerPosition').value);
        data.append('click_url', document.getElementById('bannerClickUrl').value);
        data.append('click_target', document.getElementById('clickTarget').value);
        data.append('alt_text', document.getElementById('bannerAltText').value);
    } else if (type === 'pre_roll') {
        data.append('video_url', document.getElementById('videoUrl').value);
        data.append('vast_tag_url', document.getElementById('vastTagUrl').value);
        data.append('video_duration', document.getElementById('videoDuration').value);
        data.append('skip_after', document.getElementById('skipAfter').value);
        data.append('click_url', document.getElementById('videoClickUrl').value);
        data.append('companion_banner_url', document.getElementById('companionBannerUrl').value);
    } else if (type === 'mid_roll') {
        data.append('video_url', document.getElementById('midVideoUrl').value);
        data.append('vast_tag_url', document.getElementById('midVastTagUrl').value);
        data.append('video_duration', document.getElementById('midVideoDuration').value);
        data.append('skip_after', document.getElementById('midSkipAfter').value);
        data.append('click_url', document.getElementById('midClickUrl').value);
        data.append('midroll_offset_type', document.getElementById('midrollOffsetType').value);
        data.append('midroll_offset_value', document.getElementById('midrollOffsetValue').value);
        data.append('companion_banner_url', document.getElementById('midCompanionUrl').value);
    }

    const url = id
        ? `/admin/ads/${campaignId}/creatives/${id}/update`
        : `/admin/ads/${campaignId}/creatives/add`;

    fetch(url, { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert(d.message || 'Error saving ad');
            }
        });
}

function deleteCreative(id) {
    if (!confirm('Delete this ad?')) return;

    fetch(`/admin/ads/${campaignId}/creatives/${id}/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + csrf
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.message); });
}

// Placement Modal
function openPlacementModal() {
    document.getElementById('placementId').value = '';
    document.getElementById('placementCreative').value = '';
    document.getElementById('placementZone').value = '';
    document.getElementById('placementStatus').value = 'active';
    document.getElementById('placementPriority').value = '5';
    document.getElementById('targetingRulesContainer').innerHTML = '';
    document.getElementById('placementModalTitle').textContent = 'Add Placement';
    openModal('placementModal');
}

function editPlacement(placement) {
    document.getElementById('placementId').value = placement.id;
    document.getElementById('placementCreative').value = placement.creative_id;
    document.getElementById('placementZone').value = placement.zone_id;
    document.getElementById('placementStatus').value = placement.status;
    document.getElementById('placementPriority').value = placement.priority;

    document.getElementById('targetingRulesContainer').innerHTML = '';
    if (placement.targeting_rules) {
        placement.targeting_rules.forEach(rule => {
            addTargetingRule(rule.rule_type, rule.rule_operator, rule.rule_value);
        });
    }

    document.getElementById('placementModalTitle').textContent = 'Edit Placement';
    openModal('placementModal');
}

let targetingRuleIndex = 0;

function addTargetingRule(type, operator, values) {
    const idx = targetingRuleIndex++;
    const container = document.getElementById('targetingRulesContainer');

    const row = document.createElement('div');
    row.className = 'targeting-row';
    row.id = 'targeting-rule-' + idx;

    row.innerHTML = `
        <select class="form-input rule-type" onchange="updateRuleValueOptions(${idx}, this.value)" style="width:150px;">
            <option value="">Select type...</option>
            <option value="package" ${type === 'package' ? 'selected' : ''}>Package/Plan</option>
            <option value="channel" ${type === 'channel' ? 'selected' : ''}>Channel</option>
            <option value="category" ${type === 'category' ? 'selected' : ''}>Category</option>
            <option value="content_type" ${type === 'content_type' ? 'selected' : ''}>Content Type</option>
            <option value="platform" ${type === 'platform' ? 'selected' : ''}>Platform</option>
            <option value="schedule" ${type === 'schedule' ? 'selected' : ''}>Schedule</option>
        </select>
        <select class="form-input rule-operator" style="width:100px;">
            <option value="include" ${operator === 'include' || !operator ? 'selected' : ''}>Include</option>
            <option value="exclude" ${operator === 'exclude' ? 'selected' : ''}>Exclude</option>
        </select>
        <div class="rule-value-container" style="flex:1;min-width:150px;"></div>
        <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('targeting-rule-${idx}').remove()">Remove</button>
    `;

    container.appendChild(row);

    // Build the value input/select for the chosen type
    updateRuleValueOptions(idx, type || '', values);
}

function updateRuleValueOptions(idx, ruleType, preselected) {
    const row = document.getElementById('targeting-rule-' + idx);
    if (!row) return;
    const container = row.querySelector('.rule-value-container');
    if (!container) return;

    // Normalize preselected to an array of strings
    if (preselected && !Array.isArray(preselected)) {
        preselected = String(preselected).split(',').map(v => v.trim()).filter(v => v);
    }
    const selected = (preselected || []).map(String);

    // Determine options based on rule type
    let options = null;
    let placeholder = 'Values (comma-separated)';

    switch (ruleType) {
        case 'channel':
            options = channels.map(c => ({ value: String(c.id), label: c.name }));
            break;
        case 'category':
            options = categories.map(c => ({ value: String(c.id), label: c.name + (c.type ? ' (' + c.type + ')' : '') }));
            break;
        case 'package':
            options = packages.map(p => ({ value: String(p.id), label: p.name }));
            break;
        case 'content_type':
            options = [
                { value: 'live', label: 'Live TV' },
                { value: 'vod', label: 'VOD / Movies' },
                { value: 'series', label: 'Series' }
            ];
            break;
        case 'platform':
            options = [
                { value: 'web', label: 'Web' },
                { value: 'mobile', label: 'Mobile' },
                { value: 'tv', label: 'Smart TV' },
                { value: 'stb', label: 'Set-Top Box' }
            ];
            break;
        case 'schedule':
            placeholder = 'Time ranges (e.g. 06:00-12:00, 18:00-23:59)';
            break;
    }

    if (options) {
        // Render a multi-select checkbox dropdown
        const selectedStr = selected.join(',');
        let html = `<div class="rule-multi-select" data-values="${selectedStr}">`;
        html += `<div class="rule-multi-toggle form-input" onclick="this.nextElementSibling.classList.toggle('show')" style="cursor:pointer;min-height:36px;display:flex;align-items:center;flex-wrap:wrap;gap:4px;">`;
        html += `<span class="rule-multi-placeholder" style="color:#94a3b8;">Select...</span>`;
        html += `</div>`;
        html += `<div class="rule-multi-dropdown" style="display:none;position:absolute;z-index:100;background:var(--card-bg, #1e293b);border:1px solid rgba(255,255,255,0.1);border-radius:6px;max-height:200px;overflow-y:auto;width:100%;margin-top:2px;">`;
        options.forEach(opt => {
            const checked = selected.includes(opt.value) ? 'checked' : '';
            html += `<label style="display:flex;align-items:center;padding:6px 10px;cursor:pointer;gap:8px;font-size:0.85rem;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='transparent'">`;
            html += `<input type="checkbox" value="${opt.value}" ${checked} onchange="updateMultiSelectDisplay(${idx})" style="accent-color:var(--primary, #6366f1);"> ${opt.label}`;
            html += `</label>`;
        });
        html += `</div></div>`;
        container.innerHTML = html;
        container.style.position = 'relative';

        // Update display with preselected values
        updateMultiSelectDisplay(idx);
    } else {
        // Fallback to text input (schedule, geo, or unknown type)
        const valuesStr = selected.join(', ');
        container.innerHTML = `<input type="text" class="form-input rule-value" placeholder="${placeholder}" value="${valuesStr}" style="width:100%;">`;
        container.style.position = '';
    }
}

function updateMultiSelectDisplay(idx) {
    const row = document.getElementById('targeting-rule-' + idx);
    if (!row) return;
    const multiSelect = row.querySelector('.rule-multi-select');
    if (!multiSelect) return;

    const toggle = multiSelect.querySelector('.rule-multi-toggle');
    const checkboxes = multiSelect.querySelectorAll('input[type="checkbox"]');
    const selectedValues = [];
    const selectedLabels = [];

    checkboxes.forEach(cb => {
        if (cb.checked) {
            selectedValues.push(cb.value);
            selectedLabels.push(cb.parentElement.textContent.trim());
        }
    });

    multiSelect.dataset.values = selectedValues.join(',');

    if (selectedLabels.length === 0) {
        toggle.innerHTML = '<span class="rule-multi-placeholder" style="color:#94a3b8;">Select...</span>';
    } else {
        toggle.innerHTML = selectedLabels.map(l =>
            `<span style="background:var(--primary, #6366f1);color:white;padding:2px 8px;border-radius:4px;font-size:0.75rem;">${l}</span>`
        ).join('');
    }
}

// Close multi-select dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.rule-multi-select')) {
        document.querySelectorAll('.rule-multi-dropdown.show').forEach(d => d.classList.remove('show'));
    }
});

function savePlacement() {
    const id = document.getElementById('placementId').value;

    // Gather targeting rules
    const rules = [];
    document.querySelectorAll('.targeting-row').forEach(row => {
        const type = row.querySelector('.rule-type').value;
        const operator = row.querySelector('.rule-operator').value;

        let values = [];
        const multiSelect = row.querySelector('.rule-multi-select');
        const textInput = row.querySelector('.rule-value');
        if (multiSelect) {
            // Multi-select dropdown: read checked values
            const dataValues = multiSelect.dataset.values || '';
            values = dataValues.split(',').filter(v => v);
        } else if (textInput) {
            // Text input fallback (schedule, geo)
            values = textInput.value.split(',').map(v => v.trim()).filter(v => v);
        }

        if (type && values.length > 0) {
            rules.push({
                rule_type: type,
                rule_operator: operator,
                rule_value: values
            });
        }
    });

    const data = new URLSearchParams();
    data.append('_token', csrf);
    data.append('creative_id', document.getElementById('placementCreative').value);
    data.append('zone_id', document.getElementById('placementZone').value);
    data.append('status', document.getElementById('placementStatus').value);
    data.append('priority', document.getElementById('placementPriority').value);
    data.append('targeting_rules', JSON.stringify(rules));

    const url = id
        ? `/admin/ads/${campaignId}/placements/${id}/update`
        : `/admin/ads/${campaignId}/placements/add`;

    fetch(url, { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else alert(d.message || 'Error saving placement');
        });
}

function deletePlacement(id) {
    if (!confirm('Delete this placement and its targeting rules?')) return;

    fetch(`/admin/ads/${campaignId}/placements/${id}/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + csrf
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.message); });
}
</script>
