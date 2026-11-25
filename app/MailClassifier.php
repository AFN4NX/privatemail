<?php
declare(strict_types=1);
final class MailClassifier {
    public static function classify(string $sender,string $subject,string $body=''): array {
        $s=strtolower($sender); $t=strtolower($subject."\n".$body);
        $netflix=str_contains($s,'netflix.com')||str_contains($s,'netflix.')||str_contains($t,'netflix');
        if (!$netflix) return ['category'=>'other','subcategory'=>'other','code'=>self::code($t)];
        $rules=[
            'signin_code'=>['sign-in code','signin code','verification code','code to sign in'],
            'new_device'=>['new sign-in','new device','signed in on','new login'],
            'household'=>['netflix household','household','update your netflix household'],
            'payment_refund'=>['refund','refunded','payment refund'],
            'payment_issue'=>['payment failed','payment unsuccessful','payment on hold','billing issue','payment issue'],
            'payment'=>['payment successful','payment received','payment method','your payment'],
            'billing'=>['billing','membership','plan change','next billing date'],
            'account'=>['password','account security','account information','security alert'],
            'promotional'=>['new shows','new series','watch now','what to watch','discover']
        ];
        foreach($rules as $sub=>$words) foreach($words as $w) if(str_contains($t,$w))
            return ['category'=>'netflix','subcategory'=>$sub,'code'=>self::code($t)];
        return ['category'=>'netflix','subcategory'=>'other','code'=>self::code($t)];
    }
    public static function code(string $text): ?string {
        $text=preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is',' ',$text)??$text;
        $text=html_entity_decode(strip_tags($text),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $patterns=[
            '/\b(?:verification|security code|authentication code|confirmation code|sign[ -]?in|one[ -]?time|login|access code|otp|passcode|code)\b[^0-9]{0,70}\b([0-9]{4,8})\b/i',
            '/\b([0-9]{4,8})\b[^0-9]{0,35}(?:is your|as your|verification code|sign[ -]?in code|otp)/i'
        ];
        foreach($patterns as $pattern){
            if(!preg_match_all($pattern,$text,$matches,PREG_OFFSET_CAPTURE))continue;
            foreach($matches[1] as [$code,$offset]){
                $year=(int)$code;
                if(strlen($code)===4&&$year>=1900&&$year<=2099)continue;
                $tail=substr($text,$offset+strlen($code),30);
                $before=substr($text,max(0,$offset-20),min(20,$offset));
                if(preg_match('/^\s*(?:[-\/.]\s*[0-9]|(?:USD|BDT|EUR|GBP|minutes?|hours?|days?)\b)/i',$tail))continue;
                if(preg_match('/(?:[$€£৳]\s*|order\s*#?\s*|invoice\s*#?\s*)$/iu',$before))continue;
                return $code;
            }
        }
        return null;
    }
}
