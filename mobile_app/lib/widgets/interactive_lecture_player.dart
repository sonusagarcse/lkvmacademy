import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:video_player/video_player.dart';
import '../core/constants/app_colors.dart';
import '../core/constants/api_endpoints.dart';
import '../models/lesson_model.dart';

/// Professional, fully-customized native Flutter Video Player for LKVM ADCA Learning App.
/// Uses Flutter's official [VideoPlayerController] with 100% custom Flutter controls.
/// Supports MP4, HLS (.m3u8), and direct streaming sources.
class InteractiveLecturePlayer extends StatefulWidget {
  final LessonModel lesson;
  final String courseTitle;
  final String fallbackThumbnail;
  final VoidCallback? onNextLesson;
  final VoidCallback? onPreviousLesson;
  final VoidCallback? onLessonCompleted;
  final bool hasNext;
  final bool hasPrevious;

  const InteractiveLecturePlayer({
    super.key,
    required this.lesson,
    required this.courseTitle,
    required this.fallbackThumbnail,
    this.onNextLesson,
    this.onPreviousLesson,
    this.onLessonCompleted,
    this.hasNext = false,
    this.hasPrevious = false,
  });

  static String? extractYoutubeId(String url) {
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

  @override
  State<InteractiveLecturePlayer> createState() => _InteractiveLecturePlayerState();
}

class _InteractiveLecturePlayerState extends State<InteractiveLecturePlayer> with WidgetsBindingObserver {
  VideoPlayerController? _controller;
  bool _isInitializing = false;
  String? _initError;

  // Custom Controls State
  bool _showControls = true;
  bool _isDragging = false;
  bool _isMuted = false;
  double _playbackSpeed = 1.0;
  bool _isFullscreenActive = false;
  bool _completionTriggered = false;

  Timer? _hideControlsTimer;
  Timer? _progressSaveTimer;

  static const List<double> availableSpeeds = [0.5, 0.75, 1.0, 1.25, 1.5, 1.75, 2.0];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initVideoSource();
  }

  @override
  void didUpdateWidget(covariant InteractiveLecturePlayer oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.lesson.id != widget.lesson.id ||
        oldWidget.lesson.contentUrl != widget.lesson.contentUrl ||
        oldWidget.lesson.contentType != widget.lesson.contentType) {
      _saveCurrentPosition();
      _initVideoSource();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) {
      if (_controller != null && _controller!.value.isPlaying) {
        _controller!.pause();
        _saveCurrentPosition();
      }
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _hideControlsTimer?.cancel();
    _progressSaveTimer?.cancel();
    _saveCurrentPosition();
    _disposeController();
    super.dispose();
  }

  void _disposeController() {
    _controller?.removeListener(_onControllerUpdate);
    _controller?.dispose();
    _controller = null;
  }

  static String? extractYoutubeId(String url) => InteractiveLecturePlayer.extractYoutubeId(url);

  bool get _isDirectCompatibleStream {
    final url = widget.lesson.contentUrl.trim();
    if (url.isEmpty) return false;
    if (extractYoutubeId(url) != null || widget.lesson.contentType == 'youtube') {
      return false;
    }
    if (widget.lesson.contentType == 'pdf' || url.toLowerCase().endsWith('.pdf')) {
      return false;
    }
    return true;
  }

  Future<void> _initVideoSource() async {
    _hideControlsTimer?.cancel();
    _progressSaveTimer?.cancel();
    _disposeController();

    _completionTriggered = false;
    _initError = null;

    if (!_isDirectCompatibleStream) {
      if (mounted) setState(() {});
      return;
    }

    setState(() {
      _isInitializing = true;
    });

    final rawUrl = widget.lesson.contentUrl.trim();
    final resolvedUrl = ApiEndpoints.resolveAssetUrl(rawUrl);

    try {
      final controller = VideoPlayerController.networkUrl(
        Uri.parse(resolvedUrl),
        videoPlayerOptions: VideoPlayerOptions(mixWithOthers: false),
      );

      await controller.initialize();
      if (!mounted) {
        controller.dispose();
        return;
      }

      _controller = controller;
      _controller!.addListener(_onControllerUpdate);

      // Restore saved progress
      await _restorePlaybackPosition();

      // Configure initial state
      await _controller!.setPlaybackSpeed(_playbackSpeed);
      await _controller!.setVolume(_isMuted ? 0.0 : 1.0);
      await _controller!.play();

      _isInitializing = false;
      _showControls = true;
      _startHideTimer();

      // Periodic progress save
      _progressSaveTimer = Timer.periodic(const Duration(seconds: 4), (_) {
        _saveCurrentPosition();
      });

      setState(() {});
    } catch (err) {
      if (mounted) {
        setState(() {
          _isInitializing = false;
          _initError = 'Could not load video stream: $err';
        });
      }
    }
  }

  void _onControllerUpdate() {
    if (!mounted || _controller == null) return;

    final value = _controller!.value;

    // Check completion threshold (>= 95% of duration)
    if (!_completionTriggered && value.isInitialized && value.duration > const Duration(seconds: 10)) {
      final pos = value.position;
      final total = value.duration;
      if (pos >= total * 0.95 || pos >= total - const Duration(seconds: 3)) {
        _completionTriggered = true;
        widget.onLessonCompleted?.call();
      }
    }

    // Refresh UI during playback if controls are visible or dragging
    setState(() {});
  }

  Future<void> _restorePlaybackPosition() async {
    if (_controller == null || !_controller!.value.isInitialized) return;
    try {
      final prefs = await SharedPreferences.getInstance();
      final key = 'lkvm_video_pos_${widget.lesson.id}';
      final savedSec = prefs.getInt(key) ?? 0;
      final totalSec = _controller!.value.duration.inSeconds;

      if (savedSec > 5 && savedSec < (totalSec - 8)) {
        await _controller!.seekTo(Duration(seconds: savedSec));
      }
    } catch (_) {
      // Ignore preference errors
    }
  }

  Future<void> _saveCurrentPosition() async {
    if (_controller == null || !_controller!.value.isInitialized) return;
    try {
      final pos = _controller!.value.position.inSeconds;
      final dur = _controller!.value.duration.inSeconds;
      if (pos > 0 && pos < dur) {
        final prefs = await SharedPreferences.getInstance();
        final key = 'lkvm_video_pos_${widget.lesson.id}';
        await prefs.setInt(key, pos);
      }
    } catch (_) {
      // Ignore preference errors
    }
  }

  void _startHideTimer() {
    _hideControlsTimer?.cancel();
    _hideControlsTimer = Timer(const Duration(milliseconds: 3500), () {
      if (mounted && (_controller?.value.isPlaying ?? false) && !_isDragging) {
        setState(() => _showControls = false);
      }
    });
  }

  void _toggleControls() {
    setState(() {
      _showControls = !_showControls;
    });
    if (_showControls && (_controller?.value.isPlaying ?? false)) {
      _startHideTimer();
    }
  }

  void _togglePlayPause() {
    HapticFeedback.lightImpact();
    if (_controller == null || !_controller!.value.isInitialized) return;

    if (_controller!.value.isPlaying) {
      _controller!.pause();
      setState(() => _showControls = true);
      _hideControlsTimer?.cancel();
      _saveCurrentPosition();
    } else {
      if (_controller!.value.position >= _controller!.value.duration) {
        _controller!.seekTo(Duration.zero);
      }
      _controller!.play();
      setState(() => _showControls = true);
      _startHideTimer();
    }
  }

  void _seekRelative(int seconds) {
    HapticFeedback.lightImpact();
    if (_controller == null || !_controller!.value.isInitialized) return;

    final current = _controller!.value.position;
    final total = _controller!.value.duration;
    var target = current + Duration(seconds: seconds);
    if (target < Duration.zero) target = Duration.zero;
    if (target > total) target = total;

    _controller!.seekTo(target);
    _startHideTimer();
  }

  void _setPlaybackSpeed(double speed) {
    HapticFeedback.lightImpact();
    _controller?.setPlaybackSpeed(speed);
    setState(() => _playbackSpeed = speed);
    _startHideTimer();
  }

  void _toggleMute() {
    HapticFeedback.lightImpact();
    final newMuted = !_isMuted;
    _controller?.setVolume(newMuted ? 0.0 : 1.0);
    setState(() => _isMuted = newMuted);
    _startHideTimer();
  }

  Future<void> _openFullscreen() async {
    if (_controller == null || !_controller!.value.isInitialized) return;
    HapticFeedback.mediumImpact();

    setState(() => _isFullscreenActive = true);

    await Navigator.of(context).push(
      PageRouteBuilder(
        opaque: true,
        transitionDuration: const Duration(milliseconds: 200),
        pageBuilder: (context, anim1, anim2) => _FullscreenPlayerScreen(
          controller: _controller!,
          lesson: widget.lesson,
          courseTitle: widget.courseTitle,
          playbackSpeed: _playbackSpeed,
          isMuted: _isMuted,
          hasNext: widget.hasNext,
          hasPrevious: widget.hasPrevious,
          onNextLesson: widget.onNextLesson,
          onPreviousLesson: widget.onPreviousLesson,
          onSpeedChanged: _setPlaybackSpeed,
          onMuteToggled: _toggleMute,
        ),
      ),
    );

    if (mounted) {
      setState(() => _isFullscreenActive = false);
      _startHideTimer();
    }
  }

  void _showSpeedModal() {
    _hideControlsTimer?.cancel();
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1E293B),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                  child: Row(
                    children: [
                      Icon(Icons.speed_rounded, color: Color(0xFF0062FF), size: 20),
                      SizedBox(width: 10),
                      Text(
                        'Playback Speed',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),
                const Divider(color: Colors.white12),
                ...availableSpeeds.map((speed) {
                  final isSelected = (_playbackSpeed == speed);
                  return ListTile(
                    dense: true,
                    title: Text(
                      speed == 1.0 ? '1.0x (Normal)' : '${speed}x',
                      style: TextStyle(
                        color: isSelected ? const Color(0xFF0062FF) : Colors.white,
                        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                      ),
                    ),
                    trailing: isSelected
                        ? const Icon(Icons.check_circle_rounded, color: Color(0xFF0062FF), size: 20)
                        : null,
                    onTap: () {
                      Navigator.of(ctx).pop();
                      _setPlaybackSpeed(speed);
                    },
                  );
                }),
              ],
            ),
          ),
        );
      },
    ).whenComplete(() {
      _startHideTimer();
    });
  }

  static String _formatDuration(Duration d) {
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
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final url = widget.lesson.contentUrl.trim();
    final isPdf = widget.lesson.contentType == 'pdf' || url.toLowerCase().endsWith('.pdf');
    final ytid = extractYoutubeId(url);

    // 1. PDF Study Material
    if (isPdf && url.isNotEmpty) {
      return _buildPdfViewer(isDark, url);
    }

    // 2. YouTube-hosted Video Migration Card (Strictly unbranded, compliant, informing migration)
    if (ytid != null || widget.lesson.contentType == 'youtube') {
      return _buildYouTubeMigrationCard(isDark, url);
    }

    // 3. Direct Native Stream (MP4/HLS) using VideoPlayer
    if (_isDirectCompatibleStream) {
      return _buildNativeVideoPlayerContainer();
    }

    // 4. Fallback Lesson Notes View
    return _buildNotesContainer(isDark);
  }

  Widget _buildNativeVideoPlayerContainer() {
    return Container(
      width: double.infinity,
      color: Colors.black,
      child: AspectRatio(
        aspectRatio: 16 / 9,
        child: Stack(
          alignment: Alignment.center,
          children: [
            // Video Surface
            if (_isFullscreenActive)
              const Center(
                child: Text(
                  'Playing in Fullscreen Mode',
                  style: TextStyle(color: Colors.white70, fontSize: 13),
                ),
              )
            else if (_isInitializing)
              _buildLoadingState()
            else if (_initError != null)
              _buildErrorState()
            else if (_controller != null && _controller!.value.isInitialized)
              GestureDetector(
                onTap: _toggleControls,
                behavior: HitTestBehavior.opaque,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    Center(
                      child: AspectRatio(
                        aspectRatio: _controller!.value.aspectRatio > 0
                            ? _controller!.value.aspectRatio
                            : 16 / 9,
                        child: VideoPlayer(_controller!),
                      ),
                    ),
                    if (_controller!.value.isBuffering)
                      const Center(
                        child: CircularProgressIndicator(
                          color: Color(0xFF0062FF),
                          strokeWidth: 3.5,
                        ),
                      ),
                    // Custom Controls Overlay
                    AnimatedOpacity(
                      opacity: _showControls ? 1.0 : 0.0,
                      duration: const Duration(milliseconds: 240),
                      child: IgnorePointer(
                        ignoring: !_showControls,
                        child: _buildControlsUI(isFullscreen: false),
                      ),
                    ),
                  ],
                ),
              )
            else
              _buildLoadingState(),
          ],
        ),
      ),
    );
  }

  Widget _buildLoadingState() {
    return Container(
      color: const Color(0xFF0A0E17),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircularProgressIndicator(
              color: Color(0xFF0062FF),
              strokeWidth: 3,
            ),
            const SizedBox(height: 14),
            Text(
              'Loading secure video stream...',
              style: TextStyle(
                color: Colors.white.withOpacity(0.8),
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildErrorState() {
    return Container(
      color: const Color(0xFF0A0E17),
      padding: const EdgeInsets.all(20),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.red.withOpacity(0.15),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.error_outline_rounded, color: Colors.redAccent, size: 36),
            ),
            const SizedBox(height: 10),
            const Text(
              'Unable to Play Video Stream',
              style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 6),
            Text(
              'Please check your internet connection or verify the stream format.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white.withOpacity(0.65), fontSize: 11),
            ),
            const SizedBox(height: 14),
            ElevatedButton.icon(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0062FF),
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              icon: const Icon(Icons.refresh_rounded, size: 16),
              label: const Text('Retry Playback', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
              onPressed: _initVideoSource,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildControlsUI({required bool isFullscreen}) {
    if (_controller == null || !_controller!.value.isInitialized) {
      return const SizedBox.shrink();
    }

    final value = _controller!.value;
    final totalSec = value.duration.inSeconds > 0 ? value.duration.inSeconds.toDouble() : 1.0;
    final curSec = value.position.inSeconds.toDouble().clamp(0.0, totalSec);
    final isCompleted = value.position >= value.duration && value.duration > Duration.zero;

    return Container(
      color: Colors.black.withOpacity(0.52),
      padding: EdgeInsets.symmetric(
        horizontal: isFullscreen ? 24 : 12,
        vertical: isFullscreen ? 16 : 8,
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          // Top Header Bar
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFF0062FF),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.ondemand_video_rounded, color: Colors.white, size: 12),
                    SizedBox(width: 4),
                    Text(
                      'ADCA PLAYER',
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
              // Speed Button
              IconButton(
                iconSize: 20,
                color: Colors.white,
                tooltip: 'Speed (${_playbackSpeed}x)',
                icon: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.2),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    '${_playbackSpeed}x',
                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                  ),
                ),
                onPressed: _showSpeedModal,
              ),
              // Volume Mute Button
              IconButton(
                iconSize: 20,
                color: Colors.white,
                tooltip: _isMuted ? 'Unmute' : 'Mute',
                icon: Icon(_isMuted ? Icons.volume_off_rounded : Icons.volume_up_rounded),
                onPressed: _toggleMute,
              ),
              // Fullscreen Button
              IconButton(
                iconSize: 22,
                color: Colors.white,
                tooltip: 'Fullscreen',
                icon: const Icon(Icons.fullscreen_rounded),
                onPressed: _openFullscreen,
              ),
            ],
          ),

          // Center Transport Controls (-10s, Prev, Play/Pause, Next, +10s)
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // Previous Lesson
              if (widget.hasPrevious)
                IconButton(
                  iconSize: 26,
                  color: Colors.white70,
                  tooltip: 'Previous Lesson',
                  icon: const Icon(Icons.skip_previous_rounded),
                  onPressed: () {
                    _saveCurrentPosition();
                    widget.onPreviousLesson?.call();
                  },
                ),
              const SizedBox(width: 6),
              // Seek -10s
              IconButton(
                iconSize: 32,
                color: Colors.white,
                tooltip: 'Rewind 10s',
                icon: const Icon(Icons.replay_10_rounded),
                onPressed: () => _seekRelative(-10),
              ),
              const SizedBox(width: 14),
              // Big Center Play / Pause Button
              GestureDetector(
                onTap: _togglePlayPause,
                child: Container(
                  width: 56,
                  height: 56,
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
                    isCompleted
                        ? Icons.replay_rounded
                        : (value.isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded),
                    color: Colors.white,
                    size: 34,
                  ),
                ),
              ),
              const SizedBox(width: 14),
              // Seek +10s
              IconButton(
                iconSize: 32,
                color: Colors.white,
                tooltip: 'Forward 10s',
                icon: const Icon(Icons.forward_10_rounded),
                onPressed: () => _seekRelative(10),
              ),
              const SizedBox(width: 6),
              // Next Lesson
              if (widget.hasNext)
                IconButton(
                  iconSize: 26,
                  color: Colors.white70,
                  tooltip: 'Next Lesson',
                  icon: const Icon(Icons.skip_next_rounded),
                  onPressed: () {
                    _saveCurrentPosition();
                    widget.onNextLesson?.call();
                  },
                ),
            ],
          ),

          // Bottom Progress Scrub Bar & Elapsed / Total Duration
          Row(
            children: [
              Text(
                _formatDuration(value.position),
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
                    max: totalSec,
                    value: curSec,
                    onChanged: (val) {
                      setState(() {
                        _isDragging = true;
                      });
                    },
                    onChangeEnd: (val) {
                      _controller?.seekTo(Duration(seconds: val.round()));
                      setState(() => _isDragging = false);
                      _startHideTimer();
                    },
                  ),
                ),
              ),
              Text(
                _formatDuration(value.duration),
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
    );
  }

  Widget _buildYouTubeMigrationCard(bool isDark, String rawUrl) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF0F172A) : const Color(0xFF1E293B),
        border: const Border(
          bottom: BorderSide(color: Color(0xFF334155), width: 1.5),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFF0062FF).withOpacity(0.18),
              shape: BoxShape.circle,
              border: Border.all(color: const Color(0xFF0062FF), width: 1.5),
            ),
            child: const Icon(Icons.cloud_upload_rounded, color: Color(0xFF60A5FA), size: 36),
          ),
          const SizedBox(height: 14),
          const Text(
            'Direct Video Stream Required',
            style: TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w800,
              fontSize: 16,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            widget.lesson.title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Color(0xFF93C5FD), fontSize: 13, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.3),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: Colors.white12),
            ),
            child: const Text(
              'This lecture is currently linked to an external YouTube link. To deliver a 100% unbranded, professional player experience without third-party overlays, YouTube terms require migrating video assets to a compatible direct stream provider (such as MP4, HLS .m3u8, Cloudflare Stream, or AWS S3).\n\nAdmins can update this lecture in the Admin Panel to a direct stream URL.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white70, fontSize: 11, height: 1.45),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.white,
                  side: const BorderSide(color: Colors.white30),
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                icon: const Icon(Icons.open_in_new_rounded, size: 16),
                label: const Text('Open in External App', style: TextStyle(fontSize: 12)),
                onPressed: () => _launchExternalUrl(rawUrl),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildPdfViewer(bool isDark, String rawUrl) {
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

/// Fullscreen Landscape Mode dedicated screen for immersive video playback.
class _FullscreenPlayerScreen extends StatefulWidget {
  final VideoPlayerController controller;
  final LessonModel lesson;
  final String courseTitle;
  final double playbackSpeed;
  final bool isMuted;
  final bool hasNext;
  final bool hasPrevious;
  final VoidCallback? onNextLesson;
  final VoidCallback? onPreviousLesson;
  final ValueChanged<double> onSpeedChanged;
  final VoidCallback onMuteToggled;

  const _FullscreenPlayerScreen({
    required this.controller,
    required this.lesson,
    required this.courseTitle,
    required this.playbackSpeed,
    required this.isMuted,
    required this.hasNext,
    required this.hasPrevious,
    this.onNextLesson,
    this.onPreviousLesson,
    required this.onSpeedChanged,
    required this.onMuteToggled,
  });

  @override
  State<_FullscreenPlayerScreen> createState() => _FullscreenPlayerScreenState();
}

class _FullscreenPlayerScreenState extends State<_FullscreenPlayerScreen> {
  bool _showControls = true;
  bool _isDragging = false;
  late double _currentSpeed;
  late bool _currentMuted;
  Timer? _hideTimer;

  @override
  void initState() {
    super.initState();
    _currentSpeed = widget.playbackSpeed;
    _currentMuted = widget.isMuted;

    // Lock to landscape and enter immersive sticky mode
    SystemChrome.setPreferredOrientations([
      DeviceOrientation.landscapeLeft,
      DeviceOrientation.landscapeRight,
    ]);
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);

    widget.controller.addListener(_onUpdate);
    _startHideTimer();
  }

  @override
  void dispose() {
    _hideTimer?.cancel();
    widget.controller.removeListener(_onUpdate);

    // Restore portrait orientation and normal system UI mode
    SystemChrome.setPreferredOrientations([
      DeviceOrientation.portraitUp,
    ]);
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);

    super.dispose();
  }

  void _onUpdate() {
    if (mounted) setState(() {});
  }

  void _startHideTimer() {
    _hideTimer?.cancel();
    _hideTimer = Timer(const Duration(milliseconds: 3500), () {
      if (mounted && widget.controller.value.isPlaying && !_isDragging) {
        setState(() => _showControls = false);
      }
    });
  }

  void _toggleControls() {
    setState(() => _showControls = !_showControls);
    if (_showControls && widget.controller.value.isPlaying) {
      _startHideTimer();
    }
  }

  void _seekRelative(int seconds) {
    HapticFeedback.lightImpact();
    final cur = widget.controller.value.position;
    final total = widget.controller.value.duration;
    var target = cur + Duration(seconds: seconds);
    if (target < Duration.zero) target = Duration.zero;
    if (target > total) target = total;
    widget.controller.seekTo(target);
    _startHideTimer();
  }

  void _togglePlayPause() {
    HapticFeedback.lightImpact();
    if (widget.controller.value.isPlaying) {
      widget.controller.pause();
      setState(() => _showControls = true);
      _hideTimer?.cancel();
    } else {
      if (widget.controller.value.position >= widget.controller.value.duration) {
        widget.controller.seekTo(Duration.zero);
      }
      widget.controller.play();
      setState(() => _showControls = true);
      _startHideTimer();
    }
  }

  void _showSpeedModal() {
    _hideTimer?.cancel();
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1E293B),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                  child: Row(
                    children: [
                      Icon(Icons.speed_rounded, color: Color(0xFF0062FF), size: 20),
                      SizedBox(width: 10),
                      Text(
                        'Playback Speed',
                        style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                ),
                const Divider(color: Colors.white12),
                ..._InteractiveLecturePlayerState.availableSpeeds.map((speed) {
                  final isSelected = (_currentSpeed == speed);
                  return ListTile(
                    dense: true,
                    title: Text(
                      speed == 1.0 ? '1.0x (Normal)' : '${speed}x',
                      style: TextStyle(
                        color: isSelected ? const Color(0xFF0062FF) : Colors.white,
                        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                      ),
                    ),
                    trailing: isSelected
                        ? const Icon(Icons.check_circle_rounded, color: Color(0xFF0062FF), size: 20)
                        : null,
                    onTap: () {
                      Navigator.of(ctx).pop();
                      widget.controller.setPlaybackSpeed(speed);
                      widget.onSpeedChanged(speed);
                      setState(() => _currentSpeed = speed);
                    },
                  );
                }),
              ],
            ),
          ),
        );
      },
    ).whenComplete(() {
      _startHideTimer();
    });
  }

  static String _formatDuration(Duration d) {
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    if (d.inHours > 0) {
      return '${d.inHours}:$m:$s';
    }
    return '$m:$s';
  }

  @override
  Widget build(BuildContext context) {
    final val = widget.controller.value;
    final totalSec = val.duration.inSeconds > 0 ? val.duration.inSeconds.toDouble() : 1.0;
    final curSec = val.position.inSeconds.toDouble().clamp(0.0, totalSec);
    final isCompleted = val.position >= val.duration && val.duration > Duration.zero;

    return Scaffold(
      backgroundColor: Colors.black,
      body: GestureDetector(
        onTap: _toggleControls,
        behavior: HitTestBehavior.opaque,
        child: Stack(
          fit: StackFit.expand,
          children: [
            // Video Layer
            Center(
              child: AspectRatio(
                aspectRatio: val.aspectRatio > 0 ? val.aspectRatio : 16 / 9,
                child: VideoPlayer(widget.controller),
              ),
            ),

            if (val.isBuffering)
              const Center(
                child: CircularProgressIndicator(
                  color: Color(0xFF0062FF),
                  strokeWidth: 4,
                ),
              ),

            // Fullscreen Controls Overlay
            AnimatedOpacity(
              opacity: _showControls ? 1.0 : 0.0,
              duration: const Duration(milliseconds: 240),
              child: IgnorePointer(
                ignoring: !_showControls,
                child: Container(
                  color: Colors.black.withOpacity(0.55),
                  padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 18),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      // Header
                      Row(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.arrow_back_rounded, color: Colors.white, size: 24),
                            tooltip: 'Exit Fullscreen',
                            onPressed: () => Navigator.of(context).pop(),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  widget.lesson.title,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 15,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  widget.courseTitle,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: Colors.white70,
                                    fontSize: 12,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          // Speed
                          TextButton.icon(
                            style: TextButton.styleFrom(
                              foregroundColor: Colors.white,
                              backgroundColor: Colors.white.withOpacity(0.2),
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                            ),
                            icon: const Icon(Icons.speed_rounded, size: 16),
                            label: Text('${_currentSpeed}x', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                            onPressed: _showSpeedModal,
                          ),
                          const SizedBox(width: 10),
                          // Volume Mute
                          IconButton(
                            iconSize: 22,
                            color: Colors.white,
                            tooltip: _currentMuted ? 'Unmute' : 'Mute',
                            icon: Icon(_currentMuted ? Icons.volume_off_rounded : Icons.volume_up_rounded),
                            onPressed: () {
                              widget.onMuteToggled();
                              setState(() => _currentMuted = !_currentMuted);
                            },
                          ),
                          // Exit Fullscreen
                          IconButton(
                            iconSize: 24,
                            color: Colors.white,
                            tooltip: 'Exit Fullscreen',
                            icon: const Icon(Icons.fullscreen_exit_rounded),
                            onPressed: () => Navigator.of(context).pop(),
                          ),
                        ],
                      ),

                      // Center Transport Controls
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          if (widget.hasPrevious)
                            IconButton(
                              iconSize: 32,
                              color: Colors.white,
                              tooltip: 'Previous Lesson',
                              icon: const Icon(Icons.skip_previous_rounded),
                              onPressed: () {
                                Navigator.of(context).pop();
                                widget.onPreviousLesson?.call();
                              },
                            ),
                          const SizedBox(width: 18),
                          IconButton(
                            iconSize: 38,
                            color: Colors.white,
                            tooltip: 'Rewind 10s',
                            icon: const Icon(Icons.replay_10_rounded),
                            onPressed: () => _seekRelative(-10),
                          ),
                          const SizedBox(width: 24),
                          GestureDetector(
                            onTap: _togglePlayPause,
                            child: Container(
                              width: 64,
                              height: 64,
                              decoration: BoxDecoration(
                                gradient: const LinearGradient(
                                  colors: [Color(0xFF0058F6), Color(0xFF0066FF)],
                                ),
                                shape: BoxShape.circle,
                                boxShadow: [
                                  BoxShadow(
                                    color: const Color(0xFF0062FF).withOpacity(0.55),
                                    blurRadius: 20,
                                    offset: const Offset(0, 4),
                                  ),
                                ],
                              ),
                              child: Icon(
                                isCompleted
                                    ? Icons.replay_rounded
                                    : (val.isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded),
                                color: Colors.white,
                                size: 40,
                              ),
                            ),
                          ),
                          const SizedBox(width: 24),
                          IconButton(
                            iconSize: 38,
                            color: Colors.white,
                            tooltip: 'Forward 10s',
                            icon: const Icon(Icons.forward_10_rounded),
                            onPressed: () => _seekRelative(10),
                          ),
                          const SizedBox(width: 18),
                          if (widget.hasNext)
                            IconButton(
                              iconSize: 32,
                              color: Colors.white,
                              tooltip: 'Next Lesson',
                              icon: const Icon(Icons.skip_next_rounded),
                              onPressed: () {
                                Navigator.of(context).pop();
                                widget.onNextLesson?.call();
                              },
                            ),
                        ],
                      ),

                      // Bottom Progress Bar
                      Row(
                        children: [
                          Text(
                            _formatDuration(val.position),
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Expanded(
                            child: SliderTheme(
                              data: SliderThemeData(
                                trackHeight: 4,
                                activeTrackColor: const Color(0xFF0062FF),
                                inactiveTrackColor: Colors.white24,
                                thumbColor: Colors.white,
                                thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 7),
                                overlayColor: const Color(0xFF0062FF).withOpacity(0.25),
                                overlayShape: const RoundSliderOverlayShape(overlayRadius: 14),
                              ),
                              child: Slider(
                                min: 0.0,
                                max: totalSec,
                                value: curSec,
                                onChanged: (v) {
                                  setState(() => _isDragging = true);
                                },
                                onChangeEnd: (v) {
                                  widget.controller.seekTo(Duration(seconds: v.round()));
                                  setState(() => _isDragging = false);
                                  _startHideTimer();
                                },
                              ),
                            ),
                          ),
                          Text(
                            _formatDuration(val.duration),
                            style: const TextStyle(
                              color: Colors.white70,
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
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
      ),
    );
  }
}
