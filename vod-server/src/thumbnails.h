#ifndef VOD_THUMBNAILS_H
#define VOD_THUMBNAILS_H

#include "config.h"

/**
 * Generate thumbnail sprite sheet and WebVTT file for a video.
 *
 * Creates:
 *   - {output_dir}/thumbnails/sprite.jpg (sprite sheet image)
 *   - {output_dir}/thumbnails/thumbnails.vtt (WebVTT metadata)
 *
 * Returns 0 on success, -1 on error.
 */
int thumbnails_generate(const char *source_path, const char *output_dir,
                        double duration, const vod_config_t *config);

#endif /* VOD_THUMBNAILS_H */
