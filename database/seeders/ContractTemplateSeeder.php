<?php

namespace Database\Seeders;

use App\Models\ContractTemplate;
use Illuminate\Database\Seeder;

class ContractTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Housing Contract (Uy-joy shartnomasi)
        $housingContent = <<<'HTML'
<div class="text-center font-bold" style="font-size: 14px; margin-bottom: 15px; text-align: center;">
    HISSADORLIK SHARTLARI ASOSIDA UY-JOY QURISH SHARTNOMASI № {{SHARTNOMA_RAQAMI}}
</div>
<table style="width: 100%; border: none; margin-bottom: 15px;">
    <tr>
        <td style="border: none; text-align: left; font-weight: bold;">“{{KUN}}” {{OY}} {{YIL}}-yil</td>
        <td style="border: none; text-align: right; font-weight: bold;">Urgut shahri</td>
    </tr>
</table>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    {{KORXONA_NOMI}}, Ustav asosida harakat qiluvchi direktori, {{DIREKTOR}} bir tomondan «SOTUVCHI» deb yuritiladi va ikkinchi tomondan, O`zbekiston Respublikasi Fuqarolik kodeksi asosida harakat qiluvchi, O`zbekiston Respublikasi fuqarosi, {{MIJOZ_MANZILI}}da ro`yxatda turuvchi, <strong>{{MIJOZ_FISH}}</strong> (JSHSHIR: {{MIJOZ_JSHSHIR}}, {{PASPORT_SANA}}-yilda {{PASPORT_JOYI}} tomonidan berilgan O`zbekiston Respublikasi pasporti {{PASPORT_SERIYA}}-raqamli bundan keyin «XARIDOR» deb yuritiladi, har biri alohida «tomon», birgalikda esa «tomonlar» deb ataluvchilar, mazkur shartnomani qo`yidagilar tug`risida tuzdilar:
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">1. UY-JOY QURILISHINI AMALGA OSHIRILISHINING HUQUQIY ASOSLARI.</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    1.1 «O`zshaxarsozlik loyixalash ilmiy- tadqiqot instituti» DUK bosh loyixa institutining 01.12.2020-yil №1-10/1305-sonli kelishuv xati, Urgut tuman hokimining 24.05.2021-yildagi №741-Q sonli qarori, 07.06.2021-yil №1718-1718236-21520-son Arxitektura-rejalashtirish topshirig`i, Samarqand viloyat Qurilish Bosh boshqarmasi Loyiha-smeta hujjatlarini kelishish bo`yicha Arxitektura va shaharsozlik Kengashi ishchi organining 05.08.2021-yil №23470-sonli xulosasi, 25.11.2021-yil №59/A-2021-sonli «NINA STROY» MCHJ ning qurilish loyixalari Ekspert xulosasiga ko`ra «SHER-DOR» xususiy korxonasiga Urgut shahar Do`stlik mahallasi Navoiy ko`chasi №83-uy manzilida ko`p qavatli turar-joy binolari qurilishga ruxsat berilgan.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">2. SHARTNOMA MAVZUSI</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    2.1 «SOTUVCHI» Urgut shahar Do`stlik mahallasi Navoiy ko`chasi №83-uy manzilida joylashgan yangidan qurilayotgan ko`p qavatli xonadonni (bundan buyon matnda «OBYEKT» deb yuritiladi) o`z mablag`lari yoki «XARIDOR» mablag`lari hisobidan qurish majburiyatini oladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    2.2 «SOTUVCHI» ushbu shartnomaning 2.3-bandida ko`rsatilgan xonadonni shartnomada ko`rsatilgan tartibda va muddatlarda o`zi yoki jalb qilingan shaxslar orqali qurishni va mazkur xonadonni foydalanishqa qabul qilishga ruxsat olinib, ekspluatatsiyaga kiritishga ruxsat olgach, «Hissador» ning mulkiy egaligiga topshirish majburiyatini oladi, «Hissador» esa mazkur shartnomada ko`rsatilgan xonadonning qiymatini to`lash va ekspluatatsiyaga topshirishga ruxsat mavjud bo`lganda, uni o`z mulkiy egaliga qabul qilib olish majburiyatini oladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    2.3 Loyiha smeta hujjatlari va tomonlarning kelishuviga ko`ra xonadon <strong>{{MAYDON}}</strong> kv.metrdan iborat bo`lib, Urgut shahar Do`stlik mahallasi Navoiy ko`chasi №83-uy manzilida joylashgan yangidan qurilayotgan ko`p qavatli xonadonlarning <strong>{{BLOK}}</strong>-blok <strong>{{QAVAT}}</strong>-qavati, <strong>{{XONA_RAQAMI}}</strong>-xonadoni. Xonadon maydonining umumiy hajmi loyiha bo`yicha uyning yashashga mo`ljallangan xonalar, yordamchi xonalar, yozgi xonalar maydonining yig`indisidan iborat. Xonadon tavsifi ushbu shartnomaning ajralmas qismi bo`lib hisoblanuvchi xonadon reja chizmasida keltirilgan (1-ilova).
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    2.4 Xonadon qurilish-montaj ishlarining tarkibi va ko`lami qurilish tugallangandan so`ng mulkchilikga «XARIDOR» ga o`tkaziladi, tasdiqlangan «OBYEKT» loyihasiga mos kelishi kerak.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    2.5 Yuqorida keltirilgan xonadon «SOTUVCHI» tomonidan «XARIDOR» ga quyidagi shartlar asosida o`tkaziladi:
</p>
<ul style="margin-left: 20px; margin-bottom: 12px; line-height: 1.4;">
    <li>Xonadonda quyidagi ishlar amalga oshiriladi:</li>
    <li>elektr ishlari: uy joyni loyixaga muvofiq elektrlashtirish, elektr hisoblagich o`rnatilishiga qo`nish joyidagi kommutatorga elektr simini yetkazib berish;</li>
    <li>sanitariya ishlari: sovuq suv, kanalizatsiyaning asosiy trubalarini uy-joy shaxtalariga o`rnatish;</li>
    <li>telefon va internet: Telefon va internet simlari qo`nish joyidagi kommutatorga ulash;</li>
    <li>kirish eshigi, va tashqi deraza romlari, oynalarini o`rnatish (podokonniksiz);</li>
    <li>loyiha bo`yicha binoni tugatish: plitkalar, devorlarni bo`yash.</li>
    <li>tashqi kirish eshigini o`rnatish;</li>
</ul>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    Boshqa qurilish ishlarining qiymati xonadonning qiymatiga kiritilmagan. Uy-joyda ushbu shartnomada nazarda tutilmagan boshqa qurilish ishlari alohida shartnoma shartlariga ko`ra «Quruvchi» tomonidan qurib berilishi mumkin.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    2.6 Xonadonni Kadastr idorasi orqali rasmiylashtirilib olgandan so`ng «XARIDOR» ga egalik qilish huquqini vujudga kelishi bilan bog`liq bo`lgan huquq va majburiyatlari vujudga keladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    2.7 «OBYEKT» ni «XARIDOR» ga qurib bitkazib berishning taxminiy muddati shartnoma imzolangan kundan boshlab ikki yil ichida.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">3. MOLIYALASHTIRISH MIQDORI VA TARTIBI</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.1 2.3-bandiga muvofiq «XARIDOR» tomonidan to`lanadigan to`lov miqdori:
</p>
<table style="width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 10px; border: 1px solid #000;">
    <thead>
        <tr style="background-color: #f3f4f6;">
            <th style="border: 1px solid #000; padding: 6px;">Blok</th>
            <th style="border: 1px solid #000; padding: 6px;">Qavat</th>
            <th style="border: 1px solid #000; padding: 6px;">Xonalar soni</th>
            <th style="border: 1px solid #000; padding: 6px;">Xonadonning umumiy maydoni (kv.m.)</th>
            <th style="border: 1px solid #000; padding: 6px;">1 kv.m uchun narxi QQS bilan</th>
            <th style="border: 1px solid #000; padding: 6px;">Xonadonning umumiy narxi QQS bilan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="border: 1px solid #000; padding: 6px; text-align: center;">{{BLOK}}</td>
            <td style="border: 1px solid #000; padding: 6px; text-align: center;">{{QAVAT}}</td>
            <td style="border: 1px solid #000; padding: 6px; text-align: center;">{{XONALAR_SONI}}</td>
            <td style="border: 1px solid #000; padding: 6px; text-align: center;">{{MAYDON}}</td>
            <td style="border: 1px solid #000; padding: 6px; text-align: center;">{{KV_METR_NARXI}} so'm</td>
            <td style="border: 1px solid #000; padding: 6px; text-align: center;"><strong>{{UMUMIY_NARX}} so'm</strong></td>
        </tr>
    </tbody>
</table>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    QQS bilan tuzilgan shartnomaning umumiy so`mmasi <strong>{{UMUMIY_NARX}} ({{UMUMIY_NARX_SOZ_BILAN}})</strong> so`m
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.2 3.1-bandda ko`rsatilgan shartnoma so`mmasining kamida {{BOSHLANGICH_TOLOV_FOIZ}}%, <strong>{{BOSHLANGICH_TOLOV}} ({{BOSHLANGICH_TOLOV_SOZ_BILAN}})</strong> so`m miqdoridagi mablag` «XARIDOR» tomonidan shartnoma imzolangan kundan boshlab 6 (olti) bank ish kuni ichida, qolgan so`mma boshlang`ich to`lov to`langandan so`ng <strong>{{MUDDAT_OY}} ({{MUDDAT_OY_SOZ_BILAN}})</strong> oy ichida ushbu shartnomaning 2 ilovasiga asosan to`lov grafigi asosida to`lanadi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.3 Agarda «XARIDOR» 3.2-bandda ko`rsatilgan muddatlar tugagandan so`ng dastlabki to`lovning to`liq yoki qisman miqdorini to`lamasa shartnomaning 6.2-bandiga asosan «SOTUVCHI» tomonidan bekor qilinadi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.4 To`lov shakli «XARIDOR» ning shaxsiy hissasidan korxonaning hisob-kitob hisobvarag`iga pul o`tkazish yo`li bilan va boshqa qonun xujjatlariga zid bo`lmagan boshqa shaklda amalga oshiriladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.5 3.1- bandda ko`rsatilgan shartnomaning umumiy so`mmasi yakuniy emas va «OBYEKT» ni O`z.R.Soliq qonunchiligi o`zgarganda, nazorat o`lchov natijasida uy-joy maydoni o`zgarganda, qurilishda va qurilish xom-ashyolarining va uskunalarining, energiya manbalari, inshoatlari, pudratchilarning uskunalari va xizmatlari narxlari darajasining o`zgarishiga qarab o`zgartirilishi mumkin.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.6 «OBYEKT» ning qurilish qiymati, qurilish muddati yoki ushbu shartnoma bandlaridan biri o`zgargan taqdirda, ushbu shartnomaga yozma qo`shimcha shartnoma tuziladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.7 Xaridorning to`lov majburiyatini bajarish miqdori va sanasi, 3.1, 3.2- bandlarda ko`rsatilgan miqdordagi pul mablag`lari va sanasi hisoblanadi, yoki 3.4- band bilan ushbu shartnoma «Korxona» ning hisob-kitob hisobvarag`iga «Korxona» ga xizmat ko`rsatuvchi bankning bayonotiga binoan yoki «XARIDOR» ning ishtirok etish uchun to`lash majburiyatining bajarilgan sanasi to`lov sanasi hisoblanadi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.8 Uy-joy qurilishi jarayonida Loyiha-smeta hujjatlarida nazarda tutilgan uy-joyning va uning ichki xonalarining loyihaviy maydonidan chekinishlar bo`lishi mumkin. Tomonlar mazkur chekinishlarni sodir bo`lishi mumkinligini qabul qiladilar.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    3.9 Xonadon rasmiylashtirilib berilgunga qadar kadastr xizmati uy-joy maydonini o`lchovdan o`tkazadi va mazkur o`lchov natijalari shartnoma bo`yicha tomonlar o`rtasida yakuniy hisob-kitobni amalga oshirish uchun asos bo`lib xizmat kiladi.
    Shuningdek, qurilish (xonadon) ning «OBYEKT» maydoni o`zgargan taqdirda, texnik inventarizatsiya organlari tomonidan nazorat o`lchovidan so`ng, qurilish (xonadon) ning «OBYEKT» maydoni o`zgarishi sharti bilan (uning sifati bo`yicha talablarning buzilishi bilan bog`liq emas), tomonlarning o`zaro kelishuvi bilan shartnoma narxi yuqoriga ham pastga ham o`zgarishi mumkin. «OBYEKT» qurilish (xonadon) maydoniga muvofiq shartnoma bahosini qayta hisoblash 3.1-bandda ko`rsatilgan kvadrat metr uchun narx bo`yicha amalga oshiriladi.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">4. HISOB-KITOB TARTIBI</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    4.1 «Hissador» ushbu shartnomaning 3.1, 3.2-bandlarida ko`rsatilgan to`lovni «Quruvchi»ning hisob raqamiga to`lov grafigida (shartnomaga 2-ilova, 100% to`lov bundan mustasno) ko`rsatilgan miqdor va muddatlarda amalga oshiradi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    4.2 Uchinchi shaxslarning hisob-raqamidan to`lovni amalga oshirish taqiqlanadi.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">5. TOMONLARNING HUQUQ VA MAJBURIYATLARI</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    5.1  Ushbu shartnoma bo`yicha «Korxona» quyidagi ko`rsatilgan majburiyatlarni o`z zimmasiga oladi:
</p>
<ul style="margin-left: 20px; margin-bottom: 8px; line-height: 1.4;">
    <li>ko`p qavatli uy-joy qurilishini tashkil etib, o`zi yoki jalb qilingan pudratchilar orqali loyiha-smeta hujjatlariga asosan uy-joy qurilishini, uy-joy atrofidagi hududni obodonlashtirish ishlarini va O`zbekiston Respublikasi qonunchiligiga ko`ra uy-joyni foydalanishqa qabul qilishga ruxsat olinib, eksplutatsiyaga qabul qilish uchun zarur bo`lgan Loyiha-smeta hujjatlarida nazarda tutilmagan boshqa qurilish ishlarini amalga oshirish;</li>
    <li>«Korxona» o`z majburiyatini muddatidan oldin bajarish huquqiga ega;</li>
    <li>«Xaridor» ga yozma yoki telefon orqali xonadonning qurilish ishlari tugallanganligi va uy-joy topshirishga tayyorligi va uni qabul qilish to`g`risida xabar berish;</li>
    <li>agar «Xaridor» shartnomada ko`rsatilgan moliyaviy majburiyatlarni to`liq bajarsa, «Xaridor» ga notarial idoralarning oldi-sotdi shartnomasi orqali ushbu shartnomaning 2.3- bandida nazarda tutilgan uy-joyni mazkur shartnomaning 2.5- bandida ko`rsatilgan holatda topshirish;</li>
    <li>agar xonadon o`z vaqtida topshirilmaganligi uchun «Quruvchi» «Hissador» foydasiga har bir kechiktirilgan kun uchun ushbu shartnomaning 2.3-bandida nazarda tutilgan uy-joy qiymatining 0,01 foizi miqdorida, biroq  ushbu, umumiy so`mmaning  1 (bir) foizidan oshmaydigan miqdorda penya to`laydi.</li>
    <li>mazkur shartnoma predmeti va uni amalga oshirish uchun zarur bo`lgan barcha ishlarni ishonchnomasiz amalga oshirish;</li>
    <li>mazkur shartnomada belgilangan muddatlarda uy-joy qurilishi yakunlamasligi ehtimoli mavjud bo`lganda, «Hissador»ni ushbu haqida mazkur  shartnomaning 1.2. bandida nazarda tutilgan muddat tugashidan kamida bir oy oldin xabardor etish va qurilishi ishlarini yakunlash muddatlarini o`zgartirish taklifini yuborish.</li>
    <li>«OBYEKT» ni qabul qilish va ishga tushirish to`g`risida Davlat komissiyasi dalolatnomasi tasdiqlangan kundan boshlab olti oy mobaynida qonun hujjatlari talablariga muvofiq ishlab chiquvchi xujjatlarni tayyorlash va topshirish;</li>
    <li>kadastr idorasida «Xaridor» xonadonning mulk huquqlarini davlat ro`yxatidan o`tkazish imkoniyatini taʼminlash uchun «Korxona» ga bog`liq bo`lgan barcha xujjatlar bilan taʼminlash;</li>
</ul>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    5.2 Ushbu shartnoma bo`yicha «Hissador» qo`yida ko`rsatilgan majburiyatlarni o`z zimmasiga oladi:
</p>
<ul style="margin-left: 20px; margin-bottom: 8px; line-height: 1.4;">
    <li>ushbu shartnomada ko`rsatilgan muddat, miqdor va tartibda xonadon qiymatining to`lovini amalga oshirish;</li>
    <li>«Hissador» mazkur shartnoma bo`yicha to`lovlarni o`z vaqtida yoki to`liq to`lamaganligi uchun u «Korxona» foydasiga to`lovni kechiktirgan har bir kun uchun to`lanmagan so`mmaning 0,04% miqdorida, biroq ushbu shartnomadagi to`lanmagan qoldiq so`mmasining  30 (o`ttiz) %  dan oshmaydigan miqdorda penya to`laydi.</li>
    <li>qurilish ishlari yakunlanib, uy-joyni qabul qilib olish to`g`risidagi xabarnomani olgach, 10 kunlik muddatda uy-joyni topshirish qabul qilish dalolatnomasi orqali qabul qilib, undan so`ng, notarial tartibda rasmiylashtirib olish. Agar «Hissador» tomonidan qabul qilib olishdan bosh tortilganda «Hissador» «Quruvchi» foydasiga ushbu shartnomaning 2.3-bandida nazarda tutilgan xonadon qiymatining 20 (yigirma) foizi miqdorida jarima to`laydi va «Korxona» haridorga xonadonni qabul qilish to`g`risida xabar berilgan kundan boshlab 10 (o`n) kun ichida shartnomani bir tomonlama bekor qilishga haqli.</li>
    <li>Xonadonga nisbatan mulkiy huquqlar notarial tartibda tasdiqlangan shartnoma asosida mulkiy xuquqlar davlat ro`yxatidan o`tkazilgan paytdan boshlab vujudga keladi.</li>
    <li>Xonadonni notarial tartibda rasmiylashtirish, mulkiy huquqlarni davlat ro`yxatidan o`tkazish bo`yicha barcha xarajatlarni «Hissador» o`z hisobidan amalga oshirish;</li>
    <li>Xonadonni topshirilgan paytdan boshlab, binoning nobud bulish, unga shikast yetkazish bo`yicha barcha ehtimol va xavflar bo`yicha javob berish, uy-joyning ekspluatatsion va kommunal to`lovlarni amalga oshirish;</li>
    <li>Xonadonni topshirilgunga qadar «Quruvchi»ning yozma roziligisiz qo`yidagi ishlarni amalga oshirmaslik:</li>
    <li style="list-style-type: none; margin-left: 20px;">• Xonadonni taʼmirlash, qayta qurilish va qurish ishlarni amalga oshirmaslik (pereplanirovka, devorlarni, to`siqlarni ko`chirish yoki buzish, uy-joy kommunikatsiyasini o`zgartirish, qayta jihozlash, oyna, deraza, panjaralar o`rnatish va boshqalar);</li>
    <li style="list-style-type: none; margin-left: 20px;">• «Hissador»ning aybi bilan kelib chiquvchi har qanday avariya, buzilish va ularning oqibatlarini o`z hisobidan bartaraf etish, tegishli tiklash ishlarini amalga oshirish.</li>
</ul>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    5.3 «Quruvchi» qo`yidagi ko`rsatilgan huquqlarga ega:
</p>
<ul style="margin-left: 20px; margin-bottom: 8px; line-height: 1.4;">
    <li>zarurat tug`ilganda uy-qurilish Loyiha-smeta hujjatlariga tegishli o`zgartirish va qo`shimchalar kiritish;</li>
    <li>agar «Hissador» mazkur shartnoma bo`yicha o`z majburiyatini to`liq bajarmaguncha, xonadonni qabul qilish dalolatnomasini kechiktirish, «Hissador» ni og`zaki (telefon orqali, shaxsan) yoki yozma ravishda (xat, SMS, telegrammalar va h.k) xabardor qilib shartnomani bir tomonlama bekor qiladi.</li>
    <li>qurilish ishlari yakunlanib, uy-joyni qabul qilib olish to`g`risidagi xabarnomani «Hissador»ga yetkazilgan taqdirda, «Hissador» uy-joyni qabul qilib olishdan bosh tortganida, tegishi shartnomaviy javobgarlik choralarini qo`llagan holda zarar, moliyaviy jarimalar summalarini, hisoblangan soliq so`mmalarni hisob-kitob qilib, ushlab qolgan holda, qoldiq so`mmani «Hissador»ga qaytarish va xonadonni 3-shaxsga sotish;</li>
    <li>shartnoma bo`yicha hisob-kitob to`liq amalga oshirilgunga qadar uy-joyni «Hissador»ga topshirmaslik;</li>
</ul>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    5.4 «Hissador» qo`yida ko`rsatilgan huquqlarga ega:
    - uy-joy qabul qilinib olingach, uy-joyning arxitektura va konstruktorlik yechimlarini buzmagan holda, belgilangan tartibda arxitektura va qurilish normalariga rioya qilgan holda xonalararo devorlar qurish, quvurlar o`tkazish va boshqa qurilish ishlarini amalga oshirish.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">6. MAXSUS SHARTLAR</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    6.1 «Xaridor» tomonidan 3.2-band bo`yicha to`lovni amalga oshirish muddatlari bajarilmagan taqdirda ushbu shartnoma bo`yicha, belgilangan muddatlar tugagandan so`ng «Korxona» «Obʼyekt» ni uchinchi tomonga yangi shartnoma tuzish yo`li bilan berishga haqli, «Xaridor» bilan shartnomani avtomatik ravishda bekor qiladi. Agar «Xaridor» 3.2-bandda miqdorni to`liq to`lamasa «Korxona» «Obʼyekt» ni uchinchi shaxsga «Xaridor» bilan og`zaki (telefon orqali, xabarnoma yoki SMS yuborish orqali) yoki yozma ravishda bekor qilish orqali yangi shartnoma tuzish huquqiga ega. «Xaridor» ga og`zaki (telefon orqali, xabarnoma yoki SMS yuborish orqali) yoki yozma ravishda xabarnoma berilgan kundan boshlab 10 (o`n) kundan keyin mazkur shartnoma bekor qilinadi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    6.2 «Xaridor» ga ushiar shartnomada ko`rsatilgan 5.3-bandning uchinchi qismiga muvofiq «Xaridor» to`lagan to`lovlar qaytariladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    6.3 Tomonlar ushbu shartnoma bo`yicha har qanday maʼlumotlarga taʼluqli bo`lgan masalalarda mahfiylikni saqlash majburiyati oladilar, ularning oshkor qilinishi tomonlarning mulkiy yoki boshqa zarar yetkazilishi mumkin. Aks holda aybdor tomon boshqa tomonga yetkazilgan zararni qoplash majburiyatini oladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    6.4 Agar ushbu shartnomaning 5.2-bandida belgilangan muddat ichida xonadonda nuqson va kamchiliklar bo`lmasayu, topshirish va qubul-qilish dalolatnomasi orqali olishdan bosh tortsa «Korxona» O`zbekiston Respublikasining amaldagi qonunchiligida belgilangan tartibda qo`shimcha dalolatnoma orqali xonadonni «Xaridor» ga berish to`g`risida bir tomonlama dalolatnoma tuzishga haqli. Shu bilan birga vaqt, texnik xizmat ko`rsatish (kommunal xizmatlar va boshqa harajatlarni o`z ichiga olgan holda) va xonadonga shikast yetkazish va boshqa zararlarni bir tomonlama dalolatnoma tuzilgan kundan boshlab «Xaridor» ga o`tkazilgan deb hisoblanadi.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">7. FORS-MAJOR HOLATLARI</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    7.1 Tomonlarga bog`liq bo`lmagan favqulodda holatlar (suv toshqini, yong`in, yer qimirlashi va boshqa ofatlar, hukumat qarorlari, harbiy holatlar va boshqa tomonlar nazorat qila olmaydigan holatlar) oqibatida mazkur shartnomada ko`rsatilgan majburiyatlar qisman yoki to`liq bajarilmasa, tomonlar Ushbu majburiyatlar bajarilmaganligi uchun javobgarlikdan ozod qilinadilar.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    7.2 Fors-major holatlari yuz berganida tomonlar bir-birlarini bu holatlar to`g`risida 1 (bir) oy ichida xabardor qiladi va shartnoma bo`yicha majburiyatlarning amalga oshirishining kechiktirilishi borasida yoxud shartnomaga qo`shimchalar kiritish borasida muzokara olib boradilar.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    7.3 Agarda fors-major holatlari 6 (olti) oydan ko`p davom etsa, tomonlar shartnoma bo`yicha majburiyatlarning amalga oshirishning alternativ usulini tanlashlari yoxud shartnomani bekor qilishlari mumkin.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    7.4 Fors-major holatlari vakolatli davlat organlar tomonidan tasdiqlanishi lozim.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    7.5 Fors-major holatlari kuchga kirgan vaqt shartnoma bo`yicha majburiyatlarni bajarish muddatiga kirmaydi.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">8. NIZOLARNI XAL KILISH TARTIBI.</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    8.1 Ushbu shartnoma buyicha yoki u bilan bog`lik xolda tomonlar o`rtasida kelib chiqadigan barcha nizo va ziddiyatlar yozma talabnoma bildirish tartibiga rioya qilingan xolda hal etiladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    8.2 Bildirgan talabnomasiga qonunda belgilangan 1 (bir) oylik muddat ichida javob olmagan yoki talablari qanoatlantirilmagan taqdirda, xuquqi buzilgan deb hisoblovchi tomon, Ushbu shartnoma yuzasidan yoki u bilan bog`liq holda tomonlar o`rtasida kelib chiqadigan nizo va ziddiyatlar fuqarolik ishlari bo`yicha sudlar tomonidan ko`rib chiqiladi.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">9. SHARTNOMAGA O`ZGARTIRISH VA QO`SHIMCHALAR KIRITISH TARTIBI HAMDA UNI BEKOR QILISH ASOSLARI</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    9.1 Shartnomaga har qanday o`zgartirish va qo`shimchalar yozma ravishda bajarilgan va  tomonlarning vakolatli shaxslari tomonidan imzolangan holdagina haqiqiydir va tomonlar tasdiqlangan kundan boshlab kuchga kiradi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    9.2 Shartnoma  tomonlarning  o`zaro  kelishuviga  muvofiq yoki bir tomondan shartnoma  shartlari  jiddiy  buzilgan  taqdirda ikkinchi tomonning talabiga binoan bekor qilinishi mumkin.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    9.3 Shartnomani bir tomonlama bekor qilish yozma tarzda ogohlantirilgan tartibda amalga oshiriladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    9.4 «Hissador» tomonidan shartnoma shartlari buzilgan taqdirda «Quruvchi» «Hissador»ni yozma ogohlantirib, shartnomani bekor qilish huquqiga ega.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    9.5 Shartnomani muddatidan oldin bekor qilishga O`zbekiston Respublikasini amaldagi qonun xujjatlarida nazarda tutilgan asoslarga ko`ra, yetkazilgan zarar qoplangan holda yul quyiladi.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">10. BOSHQA SHARTLAR.</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    10.1 Shartnoma tomonlar imzolangan paytdan boshlab kuchga kiradi va shartnomaviy majburiyatlar lozim darajada bajarilgunga qadar amalda bo`ladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    10.2 Tomonlar ushbu shartnomani imzolayotgan vaqtda ular aldash, zo`ravonlik, yomon kelishuv va murakkab vaziyatlar taʼsirida emasligini tasdiqlaydilar. Ular shartnoma matnini o`qishdi va bu shartnomada qanday shartlar to`zilganligini, shuningdek, shartnoma matni tomonlarning hoxish-irodasining haqiqiy ifodasiga to`g`ri kelishini aniq tushinishdi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    10.3 Shartnoma ikki nusxada tuzilgan bo`lib, har ikkalasi xam bir xil yuridik kuchga ega va tomonlarga bir nusxadan beriladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    10.4 Shartnomaga ilovalar uning ajralmas qismini bo`lib hisoblanadi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    10.5 Tomonlar o`zlarining yuridik manzillari va boshqa rekvizitlarini o`zgartirgan taqdirda bir-birlarini 3 (uch) kunlik muddatda ushbu holat to`g`risida  xabardor qilishlari shart.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    10.6 Tomonlarga nisbatan faqatgina mazkur shartnomada belgilangan javobgarlik choralari qo`llanilishi mumkin.
</p>
<div class="font-bold" style="margin-top: 20px; margin-bottom: 5px; font-weight: bold;">11. TOMONLARNING XUQUQIY MANZILLARI VA BANK RЕKVIZITLARI:</div>
<table style="width: 100%; border: none; margin-top: 15px;">
    <tr>
        <td style="width: 50%; border: none; text-align: left; vertical-align: top;">
            <strong>«XARIDOR»</strong><br>
            F.I.Sh: {{MIJOZ_FISH}}<br>
            Pasport: {{PASPORT_SERIYA}}<br>
            Berilgan vaqti: {{PASPORT_SANA}}<br>
            Kim tomonidan: {{PASPORT_JOYI}}<br>
            Manzil: {{MIJOZ_MANZILI}}<br>
            JSHSHIR: {{MIJOZ_JSHSHIR}}<br>
            Telefon: {{MIJOZ_TELEFON}}<br><br>
            Xaridor signature: ___________________
        </td>
        <td style="width: 50%; border: none; text-align: left; vertical-align: top;">
            <strong>«KORXONA»</strong><br>
            {{KORXONA_NOMI}}<br>
            Manzil: {{KORXONA_MANZILI}}<br>
            H/R: {{KORXONA_BANK_ACCOUNT}}<br>
            Bank: {{KORXONA_BANK}}<br>
            MFO: {{KORXONA_MFO}}, INN: {{KORXONA_INN}}<br>
            Telefon: {{KORXONA_TELEFON}}<br><br>
            Direktor signature: ___________________ ({{DIREKTOR}})
        </td>
    </tr>
</table>
HTML;

        // 2. Shop Contract (Savdo do'koni shartnomasi)
        $shopContent = <<<'HTML'
<div class="text-center font-bold" style="font-size: 14px; margin-bottom: 15px; text-align: center;">
    HISSADORLIK SHARTLARI ASOSIDA SAVDO DO`KONI QURISH SHARTNOMASI № {{SHARTNOMA_RAQAMI}}
</div>
<table style="width: 100%; border: none; margin-bottom: 15px;">
    <tr>
        <td style="border: none; text-align: left; font-weight: bold;">“{{KUN}}” {{OY}} {{YIL}}-yil</td>
        <td style="border: none; text-align: right; font-weight: bold;">Urgut shahri</td>
    </tr>
</table>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    {{KORXONA_NOMI}}, Ustav asosida harakat qiluvchi direktori, {{DIREKTOR}} bir tomondan «SOTUVCHI» deb yuritiladi va ikkinchi tomondan, O`zbekiston Respublikasi Fuqarolik kodeksi asosida harakat qiluvchi, O`zbekiston Respublikasi fuqarosi, {{MIJOZ_MANZILI}}da ro`yxatda turuvchi, <strong>{{MIJOZ_FISH}}</strong> (JSHSHIR: {{MIJOZ_JSHSHIR}}, {{PASPORT_SANA}}-yilda Samarqand viloyati Urgut tumani I.I.B tomonidan berilgan O`zbekiston Respublikasi pasporti {{PASPORT_SERIYA}}-raqamli), bundan keyin «XARIDOR» deb yuritiladi, har biri alohida «tomon», birgalikda esa «tomonlar» deb ataluvchilar, mazkur shartnomani qo`yidagilar tug`risida tuzdilar:
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">1. SAVDO DO`KON QURILISHINI AMALGA OSHIRILISHINING HUQUQIY ASOSLARI.</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    1.1 «O`zshaxarsozlik loyixalash ilmiy- tadqiqot instituti» DUK bosh loyixa institutining 01.12.2020-yil №1-10/1305-sonli kelishuv xati, Urgut tuman hokimining 24.05.2021-yilda gi №741-Q sonli qarori , 07.06.2021-yil №1718-1718236-21520-son Arxitektura-rejalashtirish topshirig`i, Samarqand viloyat Qurilish Bosh boshqarmasi Loyiha-smeta hujjatlarini kelishish bo`yicha Arxitektura va shaharsozlik Kengashi ishchi organining 05.08.2021-yil №23470-sonli xulosasi, 25.11.2021-yil №59/A-2021-sonli «NINA STROY» MCHJ ning qurilish loyixalari Ekspert xulosasiga ko`ra «SHER-DOR» xususiy korxonasiga Urgut shahar Do`stlik mahallasi Navoiy ko`chasi №83-uy manzilida ko`p qavatli turar-joy binolari qurilishga ruxsat berilgan.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">2. SHARTNOMA MAVZUSI</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    2.1 «SOTUVCHI» Urgut shahar Do`stlik mahallasi Navoiy ko`chasi №83-uy manzilida joylashgan yangidan qurilayotgan savdo do`konni (bundan buyon matnda «OBYEKT» deb yuritiladi) o`z mablag`lari yoki «XARIDOR» mablag`lari hisobidan qurish majburiyatini oladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    2.2 «SOTUVCHI» ushbu shartnomaning 2.3-bandida ko`rsatilgan savdo do`konni shartnomada ko`rsatilgan tartibda va muddatlarda o`zi yoki jalb qilingan shaxslar orqali qurishni va mazkur savdo do`konni foydalanishqa qabul qilishga ruxsat olinib, ekspluatatsiyaga kiritishga ruxsat olgach, «Hissador» ning mulkiy egaligiga topshirish majburiyatini oladi, «Hissador» esa mazkur shartnomada ko`rsatilgan savdo do`konning qiymatini to`lash va ekspluatatsiyaga topshirishga ruxsat mavjud bo`lganda, uni o`z mulkiy egaliga qabul qilib olish majburiyatini oladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    2.3 Loyiha smeta hujjatlari va tomonlarning kelishuviga ko`ra savdo do`kon <strong>{{MAYDON}}</strong> kv.metrdan iborat bo`lib, Urgut shahar Do`stlik mahallasi Navoiy ko`chasi №83-uy manzilida joylashgan yangidan qurilayotgan savdo do`konlarning <strong>{{BLOK}}</strong>-blok <strong>{{QAVAT}}</strong>-qavati, <strong>{{XONA_RAQAMI}}</strong>-savdo do`koni. Savdo do`kon maydonining umumiy hajmi loyiha bo`yicha do`konning savdo uchun mo`ljallangan xonalar, yordamchi xonalar maydonining yig`indisidan iborat. Savdo do`kon tavsifi ushbu shartnomaning ajralmas qismi bo`lib hisoblanuvchi savdo do`kon reja chizmasida keltirilgan (1-ilova).
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    2.4 Savdo do`kon qurilish-montaj ishlarining tarkibi va ko`lami qurilish tugallangandan so`ng mulkchilikga «XARIDOR» ga o`tkaziladi, tasdiqlangan «OBYEKT» loyihasiga mos kelishi kerak.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    2.5 Yuqorida keltirilgan savdo do`kon «SOTUVCHI» tomonidan «XARIDOR» ga quyidagi shartlar asosida o`tkaziladi:
</p>
<ul style="margin-left: 20px; margin-bottom: 12px; line-height: 1.4;">
    <li>Savdo do`konda quyidagi ishlar amalga oshiriladi:</li>
    <li>elektr ishlari: savdo do`konni loyihaga muvofiq elektrlashtirish, elektr hisoblagich o`rnatilishiga qo`nish joyidagi kommutatorga elektr simini yetkazib berish;</li>
    <li>sanitariya ishlari: sovuq suv, kanalizatsiyaning asosiy trubalarini savdo do`kon shaxtalariga o`rnatish;</li>
    <li>kirish eshigi, tashqi deraza romlari va oynalarini o`rnatish (podokonniksiz);</li>
    <li>loyiha bo`yicha binoni tugatish: plitkalar, devorlarni bo`yash.</li>
    <li>tashqi kirish eshigini o`rnatish;</li>
</ul>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    Boshqa qurilish ishlarining qiymati savdo do`konning qiymatiga kiritilmagan. Savdo do`konda ushbu shartnomada nazarda tutilmagan boshqa qurilish ishlari alohida shartnoma shartlariga ko`ra «Quruvchi» tomonidan qurib berilishi mumkin.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    2.6 Savdo do`konni Kadastr idorasi orqali rasmiylashtirilib olgandan so`ng «XARIDOR» ga egalik qilish huquqini vujudga kelishi bilan bog`liq bo`lgan huquq va majburiyatlari vujudga keladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    2.7 «OBYEKT» ni «XARIDOR» ga qurib bitkazib berishning taxminiy muddati shartnoma imzolangan kundan boshlab ikki yil ichida.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">3. MOLIYALASHTIRISH MIQDORI VA TARTIBI</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.1 2.3-bandiga muvofiq «XARIDOR» tomonidan to`lanadigan to`lov miqdori:
</p>
<table style="width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 10px; border: 1px solid #000;">
    <thead>
        <tr style="background-color: #f3f4f6;">
            <th style="border: 1px solid #000; padding: 6px;">Blok</th>
            <th style="border: 1px solid #000; padding: 6px;">Qavat</th>
            <th style="border: 1px solid #000; padding: 6px;">Xonalar soni</th>
            <th style="border: 1px solid #000; padding: 6px;">Savdo do`konning umumiy maydoni (kv.m.)</th>
            <th style="border: 1px solid #000; padding: 6px;">1 kv.m uchun narxi QQS bilan</th>
            <th style="border: 1px solid #000; padding: 6px;">Savdo do`kon ning umumiy narxi QQS bilan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="border: 1px solid #000; padding: 6px; text-align: center;">{{BLOK}}</td>
            <td style="border: 1px solid #000; padding: 6px; text-align: center;">{{QAVAT}}</td>
            <td style="border: 1px solid #000; padding: 6px; text-align: center;">{{XONALAR_SONI}}</td>
            <td style="border: 1px solid #000; padding: 6px; text-align: center;">{{MAYDON}}</td>
            <td style="border: 1px solid #000; padding: 6px; text-align: center;">{{KV_METR_NARXI}} so'm</td>
            <td style="border: 1px solid #000; padding: 6px; text-align: center;"><strong>{{UMUMIY_NARX}} so'm</strong></td>
        </tr>
    </tbody>
</table>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    QQS bilan tuzilgan shartnomaning umumiy so`mmasi <strong>{{UMUMIY_NARX}} ({{UMUMIY_NARX_SOZ_BILAN}})</strong> so`m
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.2 3.1-banda ko`rsatilgan shartnoma so`mmasining kamida {{BOSHLANGICH_TOLOV_FOIZ}}% , <strong>{{BOSHLANGICH_TOLOV}} ({{BOSHLANGICH_TOLOV_SOZ_BILAN}})</strong> so`m miqdoridagi mablag` «XARIDOR» tomonidan shartnoma imzolangan kundan boshlab 6 (olti) bank ish kuni ichida to`lanadi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.3 Agarda «XARIDOR» 3.2 -bandda ko`rsatilgan muddatlar tugagandan so`ng dastlabki to`lovning to`liq yoki qisman miqdorini to`lamasa shartnomaning 6.2-bandiga asosan «SOTUVCHI» tomonidan bekor qilinadi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.4 To`lov shakli «XARIDOR» ning shaxsiy hissasidan korxonaning hisob-kitob hisobvarag`iga pul o`tkazish yo`li bilan va boshqa qonun xujjatlariga zid bo`lmagan boshqa shaklda amalga oshiriladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.5 3.1- bandda ko`rsatilgan shartnomaning umumiy so`mmasi yakuniy emas va «OBYEKT» ni O`zbekiston Respublikasi Soliq qonunchiligi o`zgarganda, nazorat o`lchov natijasida savdo do`kon maydoni o`zgarganda, qurilishda va qurilish xom-ashyolarining va uskunalarining, energiya manbalari, inshoatlari, pudratchilarning uskunalari va xizmatlari narxlari darajasining o`zgarishiga qarab o`zgartirilishi mumkin.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.6 «OBYEKT» ning qurilish qiymati, qurilish muddati yoki ushbu shartnoma bandlaridan biri o`zgargan taqdirda, ushbu shartnomaga yozma qo`shimcha shartnoma tuziladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.7 Xaridorning to`lov majburiyatini bajarish miqdori va sanasi, 3.1, 3.2- bandlarda ko`rsatilgan miqdordagi pul mablag`lari va sanasi hisoblanadi, yoki 3.4- band bilan ushbu shartnoma «Korxona» ning hisob-kitob hisobvarag`iga «Korxona»ga xizmat ko`rsatuvchi bankning bayonotiga binoan yoki «XARIDOR» ning ishtirok etish uchun to`lash majburiyatining bajarilgan sanasi to`lov sanasi hisoblanadi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    3.8 Savdo do`kon qurilishi jarayonida Loyiha-smeta hujjatlarida nazarda tutilgan savdo do`konning va uning ichki xonalarining loyihaviy maydonidan chekinishlar bo`lishi mumkin. Tomonlar mazkur chekinishlarni sodir bo`lishi mumkinligini qabul qiladilar.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    3.9 Savdo do`kon rasmiylashtirilib berilgunga qadar kadastr xizmati savdo do`kon maydonini o`lchovdan o`tkazadi va mazkur o`lchov natijalari shartnoma bo`yicha tomonlar o`rtasida yakuniy hisob-kitobni amalga oshirish uchun asos bo`lib xizmat qiladi.
    Shuningdek, qurilish (savdo do`kon) ning «OBYEKT» maydoni o`zgargan taqdirda, texnik inventarizatsiya organlari tomonidan nazorat o`lchovidan so`ng, qurilish (savdo do`kon ) ning «OBYEKT» maydoni o`zgarishi sharti bilan (uning sifati bo`yicha talablarning buzilishi bilan bog`liq emas), tomonlarning o`zaro kelishuvi bilan shartnoma narxi yuqoriga ham pastga ham o`zgarishi mumkin. «OBYEKT» qurilish (savdo do`kon ) maydoniga muvofiq shartnoma bahosini qayta hisoblash 3.1-bandda ko`rsatilgan kvadrat metr uchun narx bo`yicha amalga oshiriladi.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">4. HISOB-KITOB TARTIBI</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    4.1 «Hissador» ushbu shartnomaning 3.1, 3.2- bandlar ida ko`rsatilgan to`lovni «Quruvchi» ning hisob raqamiga to`lov grafigida (shartnomaga 2-ilova, 100% to`lov bundan mustasno) ko`rsatilgan miqdor va muddatlarda amalga oshiradi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    4.2 Uchinchi shaxslarning hisob-raqamidan to`lovni amalga oshirish taqiqlanadi.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">5. TOMONLARNING HUQUQ VA MAJBURIYATLARI</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    5.1 Ushbu shartnoma bo`yicha «Korxona» quyidagi ko`rsatilgan majburiyatlarni o`z zimmasiga oladi:
</p>
<ul style="margin-left: 20px; margin-bottom: 8px; line-height: 1.4;">
    <li>Savdo do`kon qurilishini tashkil etib, o`zi yoki jalb qilingan pudratchilar orqali loyiha-smeta hujjatlariga asosan savdo do`kon qurilishini, savdo do`kon atrofidagi hududni obodonlashtirish ishlarini va O`zbekiston Respublikasi qonunchiligiga ko`ra savdo do`konni foydalanishqa qabul qilishga ruxsat olinib, ekspluatatsiyaga eksplutatsiyaga qabul qilish uchun zarur bo`lgan Loyiha-smeta hujjatlarida nazarda tutilmagan boshqa qurilish ishlarini amalga oshirish;</li>
    <li>«Korxona» o`z majburiyatini muddatidan oldin bajarish huquqiga ega;</li>
    <li>«Xaridor» ga yozma yoki telefon orqali savdo do`konning qurilish ishlari tugallanganligi va savdo do`kon topshirishga tayyorligi va uni qabul qilish to`g`risida xabar berish;</li>
    <li>agar «Xaridor» shartnomada ko`rsatilgan moliyaviy majburiyatlarni to`liq bajarsa, «Xaridor» ga notarial idoralarning oldi-sotdi shartnomasi orqali ushbu shartnomaning 2.3- bandida nazarda tutilgan savdo do`konni mazkur shartnomaning 2.5-bandida ko`rsatilgan holatda topshirish;</li>
    <li>agar savdo do`kon o`z vaqtida topshirilmaganligi uchun «Quruvchi» «Hissador» foydasiga har bir kechiktirilgan kun uchun ushbu shartnomaning 2.3-bandida nazarda tutilgan savdo do`kon qiymatining 0,01 foizi miqdorida, biroq ushbu umumiy so`mmaning 1 (bir) foizidan oshmaydigan miqdorda penya to`laydi.</li>
    <li>mazkur shartnoma predmeti va uni amalga oshirish uchun zarur bo`lgan barcha ishlarni ishonchnomasiz amalga oshirish;</li>
    <li>mazkur shartnomada belgilangan muddatlarda savdo do`kon qurilishi yakunlamasligi ehtimoli mavjud bo`lganda, «Hissador» ni ushbu haqida mazkur shartnomaning 1.2 bandida nazarda tutilgan muddat tugashidan kamida bir oy oldin xabardor etish va qurilishi ishlarini yakunlash muddatlarini o`zgartirish taklifini yuborish.</li>
    <li>«OBYEKT» ni foydalanishqa qabul qilish va ishga tushirish to`g`risida Davlat komissiyasi dalolatnomasi tasdiqlangan kundan boshlab olti oy mobaynida qonun hujjatlari talablari ga muvofiq ishlab chiquvchi xujjatlarni tayyorlash va topshirish;</li>
    <li>kadastr idorasida «Xaridor», savdo do`konining mulk huquqlarini davlat ro`yxatidan o`tkazish imkoniyatini ta`minlash uchun «KORXONA» ga bog`liq bo`lgan barcha xujjatlar bilan ta`minlash;</li>
</ul>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    5.2 Ushbu shartnoma bo`yicha «Hissador» quyida ko`rsatilgan majburiyatlarni o`z zimmasiga oladi:
</p>
<ul style="margin-left: 20px; margin-bottom: 8px; line-height: 1.4;">
    <li>ushbu shartnomada ko`rsatilgan muddat, miqdor va tartibda savdo do`kon qiymatining to`lovini amalga oshirish ;</li>
    <li>«Hissador» mazkur shartnoma bo`yicha to`lovlarni o`z vaqtida yoki to`liq to`lamaganligi uchun u «Korxona» foydasiga to`lovni kechiktirgan har bir kun uchun to`lanmagan so`mmaning 0,04% miqdorida, biroq ushbu shartnomadagi to`lanmagan qoldiq so`mmasi ning 30 (o`ttiz) foizidan oshmaydigan miqdorda penya to`laydi.</li>
    <li>qurilish ishlari yakunlanib, savdo do`konni qabul qilib olish to`g`risidagi xabarnomani olgach, 10 kunlik muddatda savdo do`konni topshirish qabul qilish dalolatnomasi orqali qabul qilib, undan so`ng, notarial tartibda rasmiylashtirib olish. Agar «Hissador» tomonidan qabul qilib olishdan bosh tortilganda «Hissador» «Quruvchi» foydasiga ushbu shartnomaning 2.3-bandida nazarda tutilgan savdo do`kon qiymatining 20 (yigirma) foizi miqdorida jarima to`laydi va «Korxona» haridorga savdo do`konni qabul qilish to`g`risida xabar berilgan kundan boshlab 10 (o`n) kun ichida shartnomani bir tomonlama bekor qilishga haqli.</li>
    <li>savdo do`konga nisbatan mulkiy huquqlar notarial tartibda tasdiqlangan shartnoma asosida mulkiy xuquqlar davlat ro`yxatidan o`tkazilgan paytdan boshlab vujudga keladi.</li>
    <li>savdo do`konni notarial tartibda rasmiylashtirish, mulkiy huquqlarni davlat ro`yxatidan o`tkazish bo`yicha barcha xarajatlarni «Hissador» o`z hisobidan amalga oshirish;</li>
    <li>savdo do`konni topshirilgan paytdan boshlab, binoning nobud bulish, unga shikast yetkazish bo`yicha barcha ehtimol va xavflar bo`yicha javob berish, savdo do`konning ekspluatatsion va kommunal to`lovlarni amalga oshirish;</li>
    <li>savdo do`konni topshirilgunga qadar «Quruvchi»ning yozma roziligisiz qo`yidagi ishlarni amalga oshirmaslik:</li>
    <li style="list-style-type: none; margin-left: 20px;">• savdo do`konni ta`mirlash, qayta qurilish va qurish ishlarni amalga oshirmaslik (pereplanirovka, devorlarni, to`siqlarni ko`chirish yoki buzish, savdo do`kon kommunikatsiyasini o`zgartirish, qayta jihozlash, oyna, deraza, panjaralar o`rnatish va boshqalar);</li>
    <li style="list-style-type: none; margin-left: 20px;">• «Hissador»ning aybi bilan kelib chiquvchi har qanday avariya, buzilish va ularning oqibatlarini o`z hisobidan bartaraf etish, tegishli tiklash ishlarini amalga oshirish.</li>
</ul>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    5.3 «Quruvchi» qo`yida ko`rsatilgan huquqlarga ega:
</p>
<ul style="margin-left: 20px; margin-bottom: 8px; line-height: 1.4;">
    <li>zarurat tug`ilganda uy-qurilish Loyiha-smeta hujjatlariga tegishli o`zgartirish va qo`shimchalar kiritish;</li>
    <li>agar «Hissador» mazkur shartnoma bo`yicha o`z majburiyatini to`liq bajarmaguncha, savdo do`konni qabul qilish dalolatnomasini kechiktirish, «Hissador» ni og`zaki (telefon orqali, shaxsan) yoki yozma ravishda (xat, SMS, telegrammalar va h.k) xabardor qilib shartnomani bir tomonlama bekor qiladi.</li>
    <li>qurilish ishlari yakunlanib, savdo do`konni qabul qilib olish to`g`risidagi xabarnomani «Hissador»ga yetkazilgan taqdirda, «Hissador» savdo do`konni qabul qilib olishdan bosh tortganida, tegishi shartnomaviy javobgarlik choralarini qo`llagan holda zarar, moliyaviy jarimalar summalarini, hisoblangan soliq so`mmalarni hisob-kitob qilib, ushlab qolgan holda, qoldiq so`mmani «Hissador»ga qaytarish va savdo do`konni 3-shaxsga sotish;</li>
    <li>shartnoma bo`yicha hisob-kitob to`liq amalga oshirilgunga qadar savdo do`konni «Hissador»ga topshirmaslik;</li>
</ul>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    5.4 «Hissador» qo`yida ko`rsatilgan huquqlarga ega:
    - savdo do`kon qabul qilinib olingach, savdo do`konning arxitektura va konstruktorlik yechimlarini buzmagan holda, belgilangan tartibda arxitektura va qurilish normalariga rioya kilgan holda xonalararo devorlar qurish, quvurlar o`tkazish va boshqa qurilish ishlarini amalga oshirish.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">6. MAXSUS SHARTLAR</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    6.1 «Xaridor» tomonidan 3.2-band bo`yicha to`lovni amalga oshirish muddatlari bajarilmagan taqdirda ushbu shartnoma bo`yicha, belgilangan muddatlar tugagandan so`ng «Korxona» «Ob`yekt» ni uchinchi tomonga yangi shartnoma tuzish yo`li bilan berishga haqli, «Xaridor» bilan shartnomani avtomatik ravishda bekor qiladi. Agar «Xaridor» 3.2-bandda miqdorni to`liq to`lamasa «Korxona» «Ob`yekt» ni uchinchi shaxsga «Xaridor» bilan og`zaki (telefon orqali, xabarnoma yoki SMS yuborish orqali) yoki yozma ravishda bekor qilish orqali yangi shartnoma tuzish huquqiga ega. «Xaridor» ga og`zaki (telefon orqali, xabarnoma yoki SMS yuborish orqali) yoki yozma ravishda xabarnoma berilgan kundan boshlab 10 (o`n) kundan keyin mazkur shartnoma bekor qilinadi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    6.2 «Xaridor» ga ushbu shartnomada ko`rsatilgan 5.3-bandning uchinchi qismiga muvofiq «Xaridor» to`lagan to`lovlar qaytariladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    6.3 Tomonlar ushbu shartnoma bo`yicha har qanday ma`lumotlarga ta`luqli bo`lgan masalalarda mahfiylikni saqlash majburiyati oladilar, ularning oshkor qilinishi tomonlarning mulkiy yoki boshqa zarar yetkazilishi mumkin. Aks holda aybdor tomon boshqa tomonga yetkazilgan zararni qoplash majburiyatini oladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    6.4 Agar ushbu shartnomaning 5.2-bandida belgilangan muddat ichida savdo do`konda nuqson va kamchiliklar bo`lmasayu, topshirish va qubul-qilish dalolatnomasi orqali olishdan bosh tortsa «Korxona» O`zbekiston Respublikasining amaldagi qonunchiligida belgilangan tartibda qo`shimcha dalolatnoma orqali savdo do`konni «Xaridor»ga berish to`g`risida bir tomonlama dalolatnoma tuzishga haqli. Shu bilan birga vaqt, texnik xizmat ko`rsatish (kommunal xizmatlar va boshqa harajatlarni o`z ichiga olgan holda) va savdo do`konga shikast yetkazish va boshqa zararlarni bir tomonlama dalolatnoma tuzilgan kundan boshlab «Xaridor» ga o`tkazilgan deb hisoblanadi.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">7. FORS-MAJOR HOLATLARI</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    7.1 Tomonlarga bog`liq bo`lmagan favqulodda holatlar (suv toshqini, yong`in, yer qimirlashi va boshqa ofatlar, hukumat qarorlari, harbiy holatlar va boshqa tomonlar nazorat qila olmaydigan holatlar) oqibatida mazkur shartnomada ko`rsatilgan majburiyatlar qisman yoki to`liq bajarilmasa, tomonlar Ushbu majburiyatlar bajarilmaganligi uchun javobgarlikdan ozod qilinadilar.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    7.2 Fors-major holatlari yuz berganida tomonlar bir-birlarini bu holatlar to`g`risida 1 (bir) oy ichida xabardor qiladi va shartnoma bo`yicha majburiyatlarning amalga oshirishining kechiktirilishi borasida yoxud shartnomaga qo`shimchalar kiritish borasida muzokara olib boradilar.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    7.3 Agarda fors-major holatlari 6 (olti) oydan ko`p davom etsa, tomonlar shartnoma bo`yicha majburiyatlarning amalga oshirishning alternativ usulini tanlashlari yoxud shartnomani bekor qilishlari mumkin.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    7.4 Fors-major holatlari vakolatli davlat organlar tomonidan tasdiqlanishi lozim.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    7.5 Fors-major holatlari kuchga kirgan vaqt shartnoma bo`yicha majburiyatlarni bajarish muddatiga kirmaydi.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">8. NIZOLARNI XAL KILISH TARTIBI.</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    8.1 Ushbu shartnoma buyicha yoki u bilan bog`lik xolda tomonlar o`rtasida kelib chiqadigan barcha nizo va ziddiyatlar yozma talabnoma bildirish tartibiga rioya qilingan xolda hal etiladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    8.2 Bildirgan talabnomasiga qonunda belgilangan 1 (bir) oylik muddat ichida javob olmagan yoki talablari qanoatlantirilmagan taqdirda, xuquqi buzilgan deb hisoblovchi tomon, Ushbu shartnoma yuzasidan yoki u bilan bog`liq holda tomonlar o`rtasida kelib chiqadigan nizo va ziddiyatlar fuqarolik ishlari bo`yicha sudlar tomonidan ko`rib chiqiladi.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">9. SHARTNOMAGA O`ZGARTIRISH VA QO`SHIMCHALAR KIRITISH TARTIBI HAMDA UNI BЕKOR QILISH ASOSLARI</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    9.1 Shartnomaga har qanday o`zgartirish va qo`shimchalar yozma ravishda bajarilgan va tomonlarning vakolatli shaxslari tomonidan imzolangan holdagina haqiqiydir va tomonlar tasdiqlangan kundan boshlab kuchga kiradi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    9.2 Shartnoma tomonlarning o`zaro kelishuviga muvofiq yoki bir tomondan shartnoma shartlari jiddiy buzilgan taqdirda ikkinchi tomonning talabiga binoan bekor qilinishi mumkin.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    9.3 Shartnomani bir tomonlama bekor qilish yozma tarzda ogohlantirilgan tartibda amalga oshiriladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    9.4 «Hissador» tomonidan shartnoma shartlari buzilgan taqdirda «Quruvchi» «Hissador»ni yozma ogohlantirib, shartnomani bekor qilish huquqiga ega.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    9.5 Shartnomani muddatidan oldin bekor qilishga O`zbekiston Respublikasini amaldagi qonun xujjatlarida nazarda tutilgan asoslarga ko` ra, yetkazilgan zarar qoplangan holda yul quyiladi.
</p>
<div class="font-bold" style="margin-top: 15px; margin-bottom: 5px; font-weight: bold;">10. BOSHQA SHARTLAR.</div>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    10.1 Shartnoma tomonlar imzolangan paytdan boshlab kuchga kiradi va shartnomaviy majburiyatlar lozim darajada bajarilgunga qadar amalda bo`ladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    10.2 Tomonlar ushbu shartnomani imzolayotgan vaqtda ular aldash, zo`ravonlik, yomon kelishuv va murakkab vaziyatlar ta`sirida emasligini tasdiqlaydilar. Ular shartnoma matnini o`qishdi va bu shartnomada qanday shartlar tuzilganligini, shuningdek, shartnoma matni tomonlarning xohish-irodasining haqiqiy ifodasiga to`g`ri kelishini aniq tushinishdi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    10.3 Shartnoma ikki nusxada tuzilgan bo`lib, har ikkalasi ham bir xil yuridik kuchga ega va tomonlarga bir nusxadan beriladi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    10.4 Shartnomaga ilovalar uning ajralmas qismini bo`lib hisoblanadi.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 8px;">
    10.5 Tomonlar o`zlarining yuridik manzillari va boshqa rekvizitlarini o`zgartirgan taqdirda bir-birlarini 10 (o`n) kunlik muddatda ushbu holat to`g`risida xabardor qilishlari shart.
</p>
<p style="text-align: justify; text-indent: 20px; line-height: 1.5; margin-bottom: 12px;">
    10.6 Tomonlarga nisbatan faqatgina mazkur shartnomada belgilangan javobgarlik choralari qullanilishi mumkin.
</p>
<div class="font-bold" style="margin-top: 20px; margin-bottom: 5px; font-weight: bold;">11. TOMONLARNING XUQUQIY MANZILLARI VA BANK RЕKVIZITLARI:</div>
<table style="width: 100%; border: none; margin-top: 15px;">
    <tr>
        <td style="width: 50%; border: none; text-align: left; vertical-align: top;">
            <strong>«XARIDOR»</strong><br>
            F.I.Sh: {{MIJOZ_FISH}}<br>
            Pasport: {{PASPORT_SERIYA}}<br>
            Berilgan vaqti: {{PASPORT_SANA}}<br>
            Kim tomonidan: {{PASPORT_JOYI}}<br>
            Manzil: {{MIJOZ_MANZILI}}<br>
            JSHSHIR: {{MIJOZ_JSHSHIR}}<br>
            Telefon: {{MIJOZ_TELEFON}}<br><br>
            Xaridor signature: ___________________
        </td>
        <td style="width: 50%; border: none; text-align: left; vertical-align: top;">
            <strong>«KORXONA»</strong><br>
            {{KORXONA_NOMI}}<br>
            Manzil: {{KORXONA_MANZILI}}<br>
            H/R: {{KORXONA_BANK_ACCOUNT}}<br>
            Bank: {{KORXONA_BANK}}<br>
            MFO: {{KORXONA_MFO}}, INN: {{KORXONA_INN}}<br>
            Telefon: {{KORXONA_TELEFON}}<br><br>
            Direktor signature: ___________________ ({{DIREKTOR}})
        </td>
    </tr>
</table>
HTML;

        // Seed templates
        ContractTemplate::updateOrCreate(
            ['name' => 'Uy-joy shartnomasi'],
            [
                'type' => 'residential',
                'description' => 'Ko\'p qavatli uy-joy hissadordagi qurilish shartnomasi (Yaratilgan uy-joy shablonlari uchun).',
                'preview_html' => $housingContent
            ]
        );

        ContractTemplate::updateOrCreate(
            ['name' => 'Savdo do\'koni shartnomasi'],
            [
                'type' => 'commercial',
                'description' => 'Savdo do\'koni qurilish hissadorlik shartnomasi.',
                'preview_html' => $shopContent
            ]
        );
    }
}
