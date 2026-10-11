import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:youtube_player_iframe/youtube_player_iframe.dart';
import '../core/constants/app_colors.dart';
import '../core/constants/api_endpoints.dart';
import '../models/lesson_model.dart';

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
  YoutubePlayerController? _youtubeController;
  String? _currentYoutubeId;

  // Custom Player State ("Apna Video Player")
  bool _isPlaying = true;
  bool _isBuffering = false;
  bool _showControls = true;
  bool _isDragging = false;
  bool _showSpeedMenu = false;
  double _playbackSpeed = 1.0;
  Duration _currentPosition = Duration.zero;
  Duration _totalDuration = Duration.zero;

  Timer? _hideControlsTimer;
  Timer? _positionPollTimer;

  static const List<double> _availableSpeeds = [0.5, 0.75, 1.0, 1.25, 1.5, 1.75, 2.0];

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

  @override
  void dispose() {
    _hideControlsTimer?.cancel();
    _positionPollTimer?.cancel();
    _youtubeController?.close();
    super.dispose();
  }

  void _initPlayer() {
    final url = widget.lesson.contentUrl.trim();
    final ytid = _extractYoutubeId(url);

    if (ytid != null) {
      _currentPosition = Duration.zero;
      _totalDuration = Duration.zero;
      _isPlaying = true;
      _showControls = true;
      _showSpeedMenu = false;

      if (_youtubeController == null) {
        _currentYoutubeId = ytid;
        _youtubeController = YoutubePlayerController.fromVideoId(
          videoId: ytid,
          autoPlay: true,
          params: const YoutubePlayerParams(
            showControls: false, // Turn off YouTube native controls & branding
            showFullscreenButton: false, // Turn off YouTube fullscreen button
            showVideoAnnotations: false,
            mute: false,
            loop: false,
            playsInline: true,
            enableJavaScript: true,
            strictRelatedVideos: true,
          ),
        );
        _setupPlayerListeners();
      } else if (_currentYoutubeId != ytid) {
        _currentYoutubeId = ytid;
        _youtubeController?.loadVideoById(videoId: ytid);
      }
      _startHideTimer();
    } else {
      _currentYoutubeId = null;
    }
  }

  void _setupPlayerListeners() {
    // 1. Listen for playback state updates
    _youtubeController?.listen((event) {
      if (!mounted) return;
      if (event.playerState == PlayerState.playing) {
        if (!_isPlaying) {
          setState(() => _isPlaying = true);
          _startHideTimer();
        }
        _youtubeController?.duration.then((dur) {
          if (mounted && dur > 0) {
            setState(() {
              _totalDuration = Duration(seconds: dur.round());
            });
          }
        });
      } else if (event.playerState == PlayerState.paused ||
          event.playerState == PlayerState.ended) {
        if (_isPlaying) {
          setState(() {
            _isPlaying = false;
            _showControls = true;
          });
          _hideControlsTimer?.cancel();
        }
      }

      final isBuffering = event.playerState == PlayerState.buffering;
      if (_isBuffering != isBuffering) {
        setState(() => _isBuffering = isBuffering);
      }
    });

    // 2. High-precision position poller for custom seekbar & elapsed timer
    _positionPollTimer?.cancel();
    _positionPollTimer = Timer.periodic(const Duration(milliseconds: 500), (_) async {
      if (_youtubeController != null && _isPlaying && !_isDragging) {
        final cur = await _youtubeController?.currentTime;
        final dur = await _youtubeController?.duration;
        if (mounted && cur != null) {
          setState(() {
            _currentPosition = Duration(milliseconds: (cur * 1000).round());
            if (dur != null && dur > 0) {
              _totalDuration = Duration(milliseconds: (dur * 1000).round());
            }
          });
        }
      }
    });
  }

  void _startHideTimer() {
    _hideControlsTimer?.cancel();
    _hideControlsTimer = Timer(const Duration(milliseconds: 3200), () {
      if (mounted && _isPlaying && !_isDragging && !_showSpeedMenu) {
        setState(() => _showControls = false);
      }
    });
  }

  void _toggleControls() {
    setState(() {
      _showControls = !_showControls;
      if (!_showControls) _showSpeedMenu = false;
    });
    if (_showControls && _isPlaying && !_showSpeedMenu) {
      _startHideTimer();
    }
  }

  void _togglePlayPause() {
    HapticFeedback.lightImpact();
    if (_isPlaying) {
      _youtubeController?.pauseVideo();
      setState(() {
        _isPlaying = false;
        _showControls = true;
      });
      _hideControlsTimer?.cancel();
    } else {
      _youtubeController?.playVideo();
      setState(() => _isPlaying = true);
      _startHideTimer();
    }
  }

  void _seekRelative(int seconds) {
    HapticFeedback.lightImpact();
    final maxSec = _totalDuration.inSeconds > 0 ? _totalDuration.inSeconds : 3600;
    final newSec = (_currentPosition.inSeconds + seconds).clamp(0, maxSec);
    _youtubeController?.seekTo(seconds: newSec.toDouble(), allowSeekAhead: true);
    setState(() {
      _currentPosition = Duration(seconds: newSec);
    });
    _startHideTimer();
  }

  void _selectSpeed(double speed) {
    HapticFeedback.lightImpact();
    _youtubeController?.setPlaybackRate(speed);
    setState(() {
      _playbackSpeed = speed;
      _showSpeedMenu = false;
    });
    _startHideTimer();
  }

  void _toggleFullscreen() {
    HapticFeedback.lightImpact();
    _youtubeController?.toggleFullScreen();
  }

  static String? _extractYoutubeId(String url) {
    if (url.isEmpty) return null;
    final regExp = RegExp(
      r'(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=|shorts\/))([\w-]{11})',
      caseSensitive: false,
    );
    final match = regExp.firstMatch(url);
    if (match != null) return match.group(1);
    final trimmed = url.trim();
    if (trimmed.length == 11 && !trimmed.contains('/') && !trimmed.contains('.')) {
      return trimmed;
    }
    return null;
  }

  String _formatDuration(Duration d) {
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    if (d.inHours > 0) {
      return '${d.inHours}:$m:$s';
    }
    return '$m:$s';
  }

  Future<void> _launchExternalUrl(String url) async {
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.inAppBrowserView);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final url = widget.lesson.contentUrl.trim();
    final isPdf = widget.lesson.contentType == 'pdf' || url.toLowerCase().endsWith('.pdf');
    final ytid = _extractYoutubeId(url);

    // 1. In-App Document / PDF Material
    if (isPdf && url.isNotEmpty) {
      return _buildInAppPdfViewer(isDark, url);
    }

    // 2. Proprietary In-App Video Player (Clean video layer + Click shield + Our Top Controls)
    if (ytid != null) {
      return _buildCleanProprietaryPlayer();
    }

    // 3. Direct Video Stream (MP4/WebM)
    if (widget.lesson.contentType == 'video' && url.isNotEmpty) {
      return _buildDirectVideoPlayer(isDark, url);
    }

    // 4. Fallback Lesson Notes View
    return _buildNotesContainer(isDark);
  }

  Widget _buildCleanProprietaryPlayer() {
    if (_youtubeController == null) {
      return Container(
        width: double.infinity,
        height: 220,
        color: const Color(0xFF0F172A),
        child: const Center(
          child: CircularProgressIndicator(color: Color(0xFF0062FF)),
        ),
      );
    }

    return Container(
      width: double.infinity,
      color: Colors.black,
      child: YoutubePlayer(
        controller: _youtubeController!,
        aspectRatio: 16 / 9,
        controlsBuilder: (context, isFullscreen) {
          return _buildLayeredControls(isFullscreen);
        },
      ),
    );
  }

  Widget _buildLayeredControls(bool isFullscreen) {
    return Positioned.fill(
      child: Stack(
        children: [
          // LAYER 1: Transparent Click Shield Barrier
          // Intercepts all clicks so ZERO touches ever pass through to YouTube.
          Positioned.fill(
            child: GestureDetector(
              onTap: _toggleControls,
              behavior: HitTestBehavior.opaque,
              child: Container(color: Colors.transparent),
            ),
          ),

          // Buffering Indicator
          if (_isBuffering)
            const Center(
              child: CircularProgressIndicator(
                color: Color(0xFF0062FF),
                strokeWidth: 3,
              ),
            ),

          // LAYER 2: Our Custom Controls at the Top
          // Shows smooth semi-transparent overlay ONLY when user taps or pauses.
          // When playing, completely fades out so video is 100% clean with NO permanent gradients!
          Positioned.fill(
            child: AnimatedOpacity(
              opacity: _showControls ? 1.0 : 0.0,
              duration: const Duration(milliseconds: 240),
              child: IgnorePointer(
                ignoring: !_showControls,
                child: _buildControlsUI(isFullscreen),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildControlsUI(bool isFullscreen) {
    final maxSec = _totalDuration.inSeconds > 0 ? _totalDuration.inSeconds.toDouble() : 1.0;
    final curSec = _currentPosition.inSeconds.toDouble().clamp(0.0, maxSec);

    return Container(
      // Semi-transparent backdrop only active when controls are shown
      color: Colors.black.withOpacity(0.48),
      padding: EdgeInsets.symmetric(
        horizontal: isFullscreen ? 24 : 12,
        vertical: isFullscreen ? 16 : 8,
      ),
      child: Stack(
        children: [
          Column(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              // Top Header Bar
              Row(
                children: [
                  // App Player Badge
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: const Color(0xFF0062FF),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.play_circle_fill_rounded, color: Colors.white, size: 13),
                        SizedBox(width: 4),
                        Text(
                          'LKVM PLAYER',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            letterSpacing: 0.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      widget.lesson.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  // Speed Control Button
                  GestureDetector(
                    onTap: () {
                      setState(() {
                        _showSpeedMenu = !_showSpeedMenu;
                      });
                      if (_showSpeedMenu) {
                        _hideControlsTimer?.cancel();
                      } else {
                        _startHideTimer();
                      }
                    },
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                      decoration: BoxDecoration(
                        color: _showSpeedMenu
                            ? const Color(0xFF0062FF)
                            : Colors.white.withOpacity(0.2),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(
                          color: _showSpeedMenu ? const Color(0xFF0062FF) : Colors.white30,
                          width: 1,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.speed_rounded, color: Colors.white, size: 14),
                          const SizedBox(width: 4),
                          Text(
                            '${_playbackSpeed}x',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  // Fullscreen Toggle Button
                  IconButton(
                    iconSize: 22,
                    color: Colors.white,
                    tooltip: isFullscreen ? 'Exit Fullscreen' : 'Fullscreen',
                    icon: Icon(
                      isFullscreen
                          ? Icons.fullscreen_exit_rounded
                          : Icons.fullscreen_rounded,
                    ),
                    onPressed: _toggleFullscreen,
                  ),
                ],
              ),

              // Center Transport Controls (-10s, Play/Pause, +10s)
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  // Replay 10s
                  IconButton(
                    iconSize: 32,
                    color: Colors.white,
                    tooltip: 'Back 10s',
                    icon: const Icon(Icons.replay_10_rounded),
                    onPressed: () => _seekRelative(-10),
                  ),
                  const SizedBox(width: 24),
                  // Big Center Play / Pause Button
                  GestureDetector(
                    onTap: _togglePlayPause,
                    child: Container(
                      width: 58,
                      height: 58,
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [Color(0xFF0058F6), Color(0xFF0066FF)],
                        ),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: const Color(0xFF0062FF).withOpacity(0.55),
                            blurRadius: 18,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: Icon(
                        _isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded,
                        color: Colors.white,
                        size: 36,
                      ),
                    ),
                  ),
                  const SizedBox(width: 24),
                  // Forward 10s
                  IconButton(
                    iconSize: 32,
                    color: Colors.white,
                    tooltip: 'Forward 10s',
                    icon: const Icon(Icons.forward_10_rounded),
                    onPressed: () => _seekRelative(10),
                  ),
                ],
              ),

              // Bottom Scrub Bar & Timers
              Row(
                children: [
                  Text(
                    _formatDuration(_currentPosition),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  Expanded(
                    child: SliderTheme(
                      data: SliderThemeData(
                        trackHeight: 3.5,
                        activeTrackColor: const Color(0xFF0062FF),
                        inactiveTrackColor: Colors.white24,
                        thumbColor: Colors.white,
                        thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 6),
                        overlayColor: const Color(0xFF0062FF).withOpacity(0.2),
                        overlayShape: const RoundSliderOverlayShape(overlayRadius: 12),
                      ),
                      child: Slider(
                        min: 0.0,
                        max: maxSec,
                        value: curSec,
                        onChanged: (val) {
                          setState(() {
                            _isDragging = true;
                            _currentPosition = Duration(seconds: val.round());
                          });
                        },
                        onChangeEnd: (val) {
                          _youtubeController?.seekTo(seconds: val, allowSeekAhead: true);
                          setState(() => _isDragging = false);
                          _startHideTimer();
                        },
                      ),
                    ),
                  ),
                  Text(
                    _formatDuration(_totalDuration),
                    style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ],
          ),

          // Floating Speed Selector Menu (Positioned directly under speed button)
          if (_showSpeedMenu)
            Positioned(
              top: 42,
              right: 48,
              child: Container(
                width: 140,
                padding: const EdgeInsets.symmetric(vertical: 6),
                decoration: BoxDecoration(
                  color: const Color(0xFF1E293B),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.white24, width: 1),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.5),
                      blurRadius: 16,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      child: Text(
                        'Playback Speed',
                        style: TextStyle(
                          color: Colors.white70,
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    const Divider(color: Colors.white12, height: 6),
                    for (final speed in _availableSpeeds)
                      InkWell(
                        onTap: () => _selectSpeed(speed),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                speed == 1.0 ? '1.0x (Normal)' : '${speed}x',
                                style: TextStyle(
                                  color: _playbackSpeed == speed
                                      ? const Color(0xFF0062FF)
                                      : Colors.white,
                                  fontSize: 12,
                                  fontWeight: _playbackSpeed == speed
                                      ? FontWeight.bold
                                      : FontWeight.normal,
                                ),
                              ),
                              if (_playbackSpeed == speed)
                                const Icon(Icons.check_rounded, color: Color(0xFF0062FF), size: 14),
                            ],
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildDirectVideoPlayer(bool isDark, String rawUrl) {
    final fullUrl = ApiEndpoints.resolveAssetUrl(rawUrl);

    return Container(
      width: double.infinity,
      height: 230,
      decoration: const BoxDecoration(
        color: Color(0xFF0F172A),
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          Image.network(
            ApiEndpoints.resolveAssetUrl(widget.fallbackThumbnail),
            width: double.infinity,
            height: double.infinity,
            fit: BoxFit.cover,
            errorBuilder: (_, __, ___) => Container(color: Colors.black87),
          ),
          Container(
            color: Colors.black.withOpacity(0.55),
          ),
          Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              GestureDetector(
                onTap: () => _launchExternalUrl(fullUrl),
                child: Container(
                  width: 64,
                  height: 64,
                  decoration: const BoxDecoration(
                    color: Color(0xFF0062FF),
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(color: Colors.black45, blurRadius: 16, offset: Offset(0, 4)),
                    ],
                  ),
                  child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 38),
                ),
              ),
              const SizedBox(height: 12),
              Text(
                widget.lesson.title,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
              ),
              const SizedBox(height: 4),
              const Text(
                'Tap to play high-definition secure stream',
                style: TextStyle(color: Colors.white70, fontSize: 11),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildInAppPdfViewer(bool isDark, String rawUrl) {
    final fullUrl = ApiEndpoints.resolveAssetUrl(rawUrl);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9),
        border: Border(
          bottom: BorderSide(
            color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
          ),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.red.withOpacity(0.12),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.picture_as_pdf_rounded, color: Colors.red, size: 36),
          ),
          const SizedBox(height: 14),
          Text(
            widget.lesson.title,
            textAlign: TextAlign.center,
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 6),
          const Text(
            'Official Course Study Notes & Reading Material',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.grey, fontSize: 12),
          ),
          const SizedBox(height: 18),
          ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF0062FF),
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            icon: const Icon(Icons.open_in_new_rounded, size: 18),
            label: const Text('Open & Read PDF In-App', style: TextStyle(fontWeight: FontWeight.bold)),
            onPressed: () => _launchExternalUrl(fullUrl),
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
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: AppColors.primary.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.menu_book_rounded, color: AppColors.primaryLight, size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  widget.lesson.title,
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
          if (widget.lesson.description.isNotEmpty) ...[
            const SizedBox(height: 14),
            Text(
              widget.lesson.description,
              style: TextStyle(
                fontSize: 14,
                height: 1.5,
                color: isDark ? Colors.white70 : Colors.black87,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
