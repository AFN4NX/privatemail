<?php if(!defined('PRIVATEMAIL_AUTH_VIEW')){http_response_code(404);exit;}$goodbye=($authRobotMode??'login')==='logout';?>
<div class="robot-scene" id="authRobot" data-mode="<?=$goodbye?'logout':'login'?>" data-auth-error="<?=!empty($error)?'1':'0'?>" role="group" aria-label="PrivateMail robot mascot">
 <div class="robot-speech" id="robotSpeech" aria-live="polite"><?=Security::e($goodbye?'Heading out? I’ll keep your place.':(!empty($error)?'Try again. I’m still here.':'Hi. I’m Pip. Your inbox companion.'))?></div>
 <div class="robot-antenna" aria-hidden="true"><i></i></div>
 <div class="robot-head-position" aria-hidden="true"><div class="robot-head" id="robotHead">
  <div class="robot-side robot-front"><span class="robot-ear ear-left"></span><span class="robot-ear ear-right"></span><div class="robot-screen"><div class="robot-eyes"><i></i><i></i></div><div class="robot-mouth"></div></div><span class="robot-head-screw"></span></div>
  <div class="robot-side robot-back"><span class="robot-ear ear-left"></span><span class="robot-ear ear-right"></span><div class="robot-back-screen"><div class="robot-leds"><i></i><i></i><i></i></div><div class="robot-memory"><i></i><i></i><i></i><i></i></div><strong>NOT LOOKING</strong></div></div>
 </div></div>
</div>
