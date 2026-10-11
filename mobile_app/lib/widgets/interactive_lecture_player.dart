import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import '../core/constants/app_colors.dart';
import '../core/constants/api_endpoints.dart';
import '../models/lesson_model.dart';
import 'player_embed.dart';

class InteractiveLecturePlayer extends StatefulWidget {
  final LessonModel lesson;
  final String courseTitle;
  final String fallbackThumbnail;

  const InteractiveLecturePlayer({
    super.key,
    required this.lesson,
    required this.courseTitle,
    required this.fallbackThumbnail,
  });

  @override
  State<InteractiveLecturePlayer> createState() => _InteractiveLecturePlayerState();
}

class _InteractiveLecturePlayerState extends State<InteractiveLecturePlayer> {
  String? _youtubeId;
  String _viewKey = '';

  // Custom Player State
  bool _isPlaying = true;
  bool _isMuted = false;
  double _playbackSpeed = 1.0;
  double _currentProgress = 0.25; // 0.0 to 1.0
  bool _showControls = true;

  @override
  void initState() {
    super.initState();
    _initPlayer();
  }

  @override
  void didUpdateWidget(covariant InteractiveLecturePlayer oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.lesson.id != widget.lesson.id ||
        oldWidget.lesson.contentUrl != widget.lesson.contentUrl) {
      _initPlayer();
    }
  }

  void _initPlayer() {
    final url = widget.lesson.contentUrl.trim();
    _youtubeId = _extractYoutubeId(url);
    _viewKey = 'lms_player_${widget.lesson.id}_${DateTime.now().millisecondsSinceEpoch}';
    _isPlaying = true;
    _isMuted = false;
    _playbackSpeed = 1.0;
    _currentProgress = 0.15;

    if (kIsWeb) {
      if (_youtubeId != null) {
        // Embed YouTube with ALL branding, controls, and info hidden
        final embedUrl =
            'https://www.youtube.com/embed/$_youtubeId?autoplay=1&controls=0&disablekb=1&fs=0&modestbranding=1&rel=0&showinfo=0&iv_load_policy=3&enablejsapi=1';
        registerWebPlayer(_viewKey, embedUrl, false);
      } else if (widget.lesson.contentType == 'video' && url.isNotEmpty) {
        final resolved = ApiEndpoints.resolveAssetUrl(url);
        registerWebPlayer(_viewKey, resolved, true);
      } else if (widget.lesson.contentType == 'pdf' && url.isNotEmpty) {
        final resolved = ApiEndpoints.resolveAssetUrl(url);
        registerWebPlayer(_viewKey, '$resolved#toolbar=1&navpanes=0&view=FitH', false);
      }
    }
  }

  static String? _extractYoutubeId(String url) {
    if (url.isEmpty) return null;
    final regExp = RegExp(
      r'(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})',
      caseSensitive: false,
    );
    final match = regExp.firstMatch(url);
    return match?.group(1);
  }

  void _togglePlayPause() {
    setState(() {
      _isPlaying = !_isPlaying;
    });
    if (kIsWeb) {
      sendWebPlayerCommand(_viewKey, _isPlaying ? 'playVideo' : 'pauseVideo');
    }
  }

  void _toggleMute() {
    setState(() {
      _isMuted = !_isMuted;
    });
    if (kIsWeb) {
      sendWebPlayerCommand(_viewKey, _isMuted ? 'mute' : 'unMute');
    }
  }

  void _cycleSpeed() {
    final speeds = [1.0, 1.25, 1.5, 2.0];
    int nextIdx = (speeds.indexOf(_playbackSpeed) + 1) % speeds.length;
    setState(() {
      _playbackSpeed = speeds[nextIdx];
    });
    if (kIsWeb) {
      sendWebPlayerCommand(_viewKey, 'setPlaybackRate', _playbackSpeed);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final url = widget.lesson.contentUrl.trim();
    final isPdf = widget.lesson.contentType == 'pdf' || url.toLowerCase().endsWith('.pdf');
    final isYoutube = _youtubeId != null || widget.lesson.contentType == 'youtube';

    // 1. In-App PDF Document Viewer
    if (isPdf && url.isNotEmpty) {
      return _buildInAppPdfViewer(isDark, url);
    }

    // 2. Proprietary Custom Video Player (YouTube & MP4 Stream)
    if ((isYoutube && _youtubeId != null) || (widget.lesson.contentType == 'video' && url.isNotEmpty)) {
      return _buildCustomLmsVideoPlayer(isDark);
    }

    // 3. Fallback Notes View
    return _buildNotesContainer(isDark);
  }

  /// Proprietary Custom Video Player Widget
  /// Hides YouTube branding and overlays our own LMS controls
  Widget _buildCustomLmsVideoPlayer(bool isDark) {
    return Container(
      width: double.infinity,
      height: 250,
      decoration: const BoxDecoration(
        color: Colors.black,
      ),
      child: Stack(
        fit: StackFit.expand,
        children: [
          // Underlying Video Surface (Pointer-events blocked to prevent YouTube clickthrough)
          if (kIsWeb)
            IgnorePointer(
              ignoring: true, // Guarantees user taps hit OUR controls, not YouTube
              child: HtmlElementView(
                key: ValueKey(_viewKey),
                viewType: _viewKey,
              ),
            )
          else
            // High-res stream thumbnail on mobile
            Image.network(
              _youtubeId != null
                  ? 'https://img.youtube.com/vi/$_youtubeId/hqdefault.jpg'
                  : ApiEndpoints.resolveAssetUrl(widget.fallbackThumbnail),
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => Container(color: Colors.black87),
            ),

          // Tap gesture to reveal / hide controls
          GestureDetector(
            behavior: HitTestBehavior.translucent,
            onTap: () {
              setState(() => _showControls = !_showControls);
            },
            child: Container(
              color: Colors.black.withValues(alpha: _showControls ? 0.35 : 0.0),
            ),
          ),

          // Proprietary LMS Watermark Bar (Top)
          Positioned(
            top: 10,
            left: 12,
            right: 12,
            child: AnimatedOpacity(
              opacity: _showControls ? 1.0 : 0.0,
              duration: const Duration(milliseconds: 200),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.75),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.white12),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.shield_rounded, color: AppColors.accent, size: 13),
                        SizedBox(width: 5),
                        Text(
                          'LKVM LMS SECURE PLAYER',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withValues(alpha: 0.8),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: const Text(
                      '1080p HD',
                      style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Custom Center Play / Pause Button
          Center(
            child: AnimatedOpacity(
              opacity: _showControls ? 1.0 : 0.0,
              duration: const Duration(milliseconds: 200),
              child: GestureDetector(
                onTap: _togglePlayPause,
                child: Container(
                  width: 62,
                  height: 62,
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.9),
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.primary.withValues(alpha: 0.4),
                        blurRadius: 20,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Icon(
                    _isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded,
                    color: Colors.white,
                    size: 38,
                  ),
                ),
              ),
            ),
          ),

          // Custom Bottom Control Bar
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: AnimatedOpacity(
              opacity: _showControls ? 1.0 : 0.0,
              duration: const Duration(milliseconds: 200),
              child: Container(
                padding: const EdgeInsets.fromLTRB(14, 8, 14, 10),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Colors.transparent,
                      Colors.black.withValues(alpha: 0.88),
                    ],
                  ),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Custom Scrubber Track
                    SliderTheme(
                      data: SliderTheme.of(context).copyWith(
                        trackHeight: 3.5,
                        thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 6),
                        overlayShape: const RoundSliderOverlayShape(overlayRadius: 10),
                        activeTrackColor: AppColors.primaryLight,
                        inactiveTrackColor: Colors.white24,
                        thumbColor: Colors.white,
                      ),
                      child: Slider(
                        value: _currentProgress,
                        onChanged: (val) {
                          setState(() => _currentProgress = val);
                          if (kIsWeb) {
                            sendWebPlayerCommand(_viewKey, 'seekTo', val * 100);
                          }
                        },
                      ),
                    ),
                    const SizedBox(height: 2),

                    // Controls Row
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            GestureDetector(
                              onTap: _togglePlayPause,
                              child: Icon(
                                _isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded,
                                color: Colors.white,
                                size: 22,
                              ),
                            ),
                            const SizedBox(width: 14),
                            GestureDetector(
                              onTap: _toggleMute,
                              child: Icon(
                                _isMuted ? Icons.volume_off_rounded : Icons.volume_up_rounded,
                                color: Colors.white,
                                size: 20,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Text(
                              '03:45 / ${widget.lesson.duration}',
                              style: const TextStyle(color: Colors.white70, fontSize: 11, fontWeight: FontWeight.w600),
                            ),
                          ],
                        ),
                        Row(
                          children: [
                            // Playback Speed Button
                            GestureDetector(
                              onTap: _cycleSpeed,
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(alpha: 0.15),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  '${_playbackSpeed}x',
                                  style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            const Icon(Icons.fullscreen_rounded, color: Colors.white, size: 22),
                          ],
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// In-App PDF Document Viewer
  /// Renders PDFs right inside the app interface without external redirection
  Widget _buildInAppPdfViewer(bool isDark, String rawUrl) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9),
        border: Border(
          bottom: BorderSide(
            color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
          ),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // PDF Top Action Bar
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF0F172A) : Colors.white,
              border: Border(
                bottom: BorderSide(
                  color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
                ),
              ),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: Colors.red.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Icon(Icons.picture_as_pdf_rounded, color: Colors.red, size: 18),
                    ),
                    const SizedBox(width: 10),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          widget.lesson.title,
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                        const Text(
                          'In-App Study Document',
                          style: TextStyle(fontSize: 10, color: Colors.grey),
                        ),
                      ],
                    ),
                  ],
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Text(
                    'IN-APP READER',
                    style: TextStyle(color: AppColors.primaryLight, fontSize: 10, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
          ),

          // Embedded PDF Canvas
          SizedBox(
            height: 480,
            width: double.infinity,
            child: kIsWeb
                ? HtmlElementView(
                    key: ValueKey(_viewKey),
                    viewType: _viewKey,
                  )
                : Container(
                    color: const Color(0xFF525659),
                    padding: const EdgeInsets.all(24),
                    child: Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.menu_book_rounded, color: Colors.white70, size: 54),
                          const SizedBox(height: 14),
                          Text(
                            widget.lesson.title,
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 6),
                          const Text(
                            'Reading Course Curriculum Notes',
                            style: TextStyle(color: Colors.white60, fontSize: 12),
                          ),
                        ],
                      ),
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildNotesContainer(bool isDark) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF8FAFC),
        border: Border(
          bottom: BorderSide(
            color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
          ),
        ),
      ),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.article_rounded, color: AppColors.primaryLight, size: 36),
          ),
          const SizedBox(height: 12),
          Text(
            widget.lesson.title,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 6),
          Text(
            widget.lesson.description.isNotEmpty
                ? widget.lesson.description
                : 'Interactive Lecture Notes & Documentation',
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.grey, fontSize: 13),
          ),
        ],
      ),
    );
  }
}
