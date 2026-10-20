<?php
/** 开机启动动画（dsh-startup-screen 风格）
 *  由 public/assets/js/splash.js 编排六幕序列；按会话播放一次，可跳过。
 *  访问 ?splash=1 可强制重播。
 */
?>
<div id="gw-boot" class="gw-boot" role="presentation" aria-hidden="true">
  <div class="gw-boot-grid"></div>
  <div class="gw-boot-scan"></div>
  <div class="gw-boot-flash"></div>
  <div class="gw-boot-stage">
    <div class="gw-reticle" aria-hidden="true">
      <svg viewBox="0 0 120 120">
        <path class="gw-ring" d="M 44.95 101.36 A 44 44 0 1 1 75.05 101.36" fill="none" stroke="#ff7500" stroke-width="2.4" stroke-linecap="round"/>
        <circle cx="60" cy="60" r="3" fill="#ff7500"/>
        <line x1="60" y1="7" x2="60" y2="17" stroke="#ff7500" stroke-width="2"/>
        <line x1="60" y1="103" x2="60" y2="113" stroke="#ff7500" stroke-width="2"/>
        <line x1="7" y1="60" x2="17" y2="60" stroke="#ff7500" stroke-width="2"/>
        <line x1="103" y1="60" x2="113" y2="60" stroke="#ff7500" stroke-width="2"/>
      </svg>
    </div>
    <div class="gw-boot-word glitch" data-text="GITWEB">GITWEB</div>
    <div class="gw-boot-tag">SELF-HOSTED GIT PLATFORM</div>
    <div class="gw-dossier">
      <div class="gw-dossier-head">
        <span class="gw-dh-title">技术档案 · TECHNICAL DOSSIER</span>
        <span class="gw-dh-id">ID 0087</span>
      </div>
      <div class="gw-brand-block">
        <div class="gw-bb-1">GITWEB</div>
        <div class="gw-bb-2">DISTRIBUTED VERSION CONTROL</div>
        <div class="gw-bb-3">WORKBUDDY HARNESS</div>
      </div>
      <div class="gw-steps" id="gw-steps"></div>
      <div class="gw-progress"><span id="gw-progress-bar"></span></div>
    </div>
  </div>
  <div class="gw-reveal"><div class="gw-reveal-word">GITWEB</div></div>
  <div class="gw-boot-welcome">WELCOME, DEVELOPER</div>
  <div class="gw-boot-foot">POWERED BY WORKBUDDY · likeweixue</div>
  <button class="gw-boot-skip" id="gw-boot-skip" type="button">SKIP ✕</button>
</div>
