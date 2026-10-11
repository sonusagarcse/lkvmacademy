// ignore: avoid_web_libraries_in_flutter
import 'dart:html' as html;
import 'dart:ui_web' as ui_web;

final Map<String, html.Element> _registeredElements = {};

void registerWebPlayer(String viewType, String url, bool isVideo) {
  // ignore: undefined_prefixed_name
  ui_web.platformViewRegistry.registerViewFactory(viewType, (int viewId) {
    if (isVideo) {
      final video = html.VideoElement()
        ..src = url
        ..controls = false
        ..autoplay = true
        ..style.border = 'none'
        ..style.width = '100%'
        ..style.height = '100%';
      _registeredElements[viewType] = video;
      return video;
    } else {
      final iframe = html.IFrameElement()
        ..src = url
        ..style.border = 'none'
        ..style.width = '100%'
        ..style.height = '100%'
        ..style.pointerEvents = 'none' // Completely blocks clicks to YouTube UI / external links
        ..style.transform = 'scale(1.05)' // Hides YouTube watermark & edge bars
        ..allow = 'autoplay; encrypted-media; picture-in-picture'
        ..allowFullscreen = false;
      _registeredElements[viewType] = iframe;
      return iframe;
    }
  });
}

void sendWebPlayerCommand(String viewType, String command, [dynamic arg]) {
  final element = _registeredElements[viewType];
  if (element is html.IFrameElement) {
    final argsStr = arg != null ? ',"args":[$arg]' : ',"args":""';
    final msg = '{"event":"command","func":"$command"$argsStr}';
    element.contentWindow?.postMessage(msg, '*');
  } else if (element is html.VideoElement) {
    if (command == 'playVideo') {
      element.play();
    } else if (command == 'pauseVideo') {
      element.pause();
    } else if (command == 'mute') {
      element.muted = true;
    } else if (command == 'unMute') {
      element.muted = false;
    }
  }
}
