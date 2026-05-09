const fs = require('fs');
const path = require('path');

const TRANSLATIONS = {
    // Finance/Index.vue
    "Kirimlar (Qayerdan)": "incomes_from",
    "Chiqimlar (Qayerga)": "expenses_to",
    "Barchasi": "all",
    "🟢 Yangi": "new_native",
    "📦 Import": "imported",
    "Tahrirlash": "edit",
    "O'chirish": "delete",
    "Yuklab olish": "download",
    "Tranzaksiyani Tahrirlash": "edit_transaction",
    "Summa": "amount",
    "Hisob": "account",
    "Kompaniya Hisobi (Main)": "main_account",
    "Kredit Hisobi (Kassa)": "credit_account",
    "Izoh (Qayerga/Kimdan)": "notes_direction",
    "Bekor qilish": "cancel",
    "Saqlash": "save",
    "Izoh:": "notes_label",
    "Mijoz:": "client_label",
    "So'rovchi (Maker):": "maker_label",
    "📍 Aniq GPS Lokatsiya": "exact_gps_location",
    "📞 Bog'lanish": "contact_phone",
    "✔️ Tasdiq": "approve_check",
    "❌ Rad etish": "reject_cross",
    "✈️ TELEGRAM": "source_telegram",
    "📱 HAMKOR ILOVASI": "source_partner_app",
    "💻 ADMIN PANEL (OPERATOR)": "source_admin_panel",
    "Qarzlar (Liabilities)": "liabilities",
    "Ta'minotchilar": "suppliers",
    "O'tkazmalar": "transfers",
    "Cheklar": "receipts",
    "Tasdiqlar": "approvals",
    "Kirim": "income",
    "Chiqim": "expense",
    "Tasdiqlovchilar Navbati": "maker_checker_queue",
    "Tranzaksiyani Tahrirlash": "edit_transaction",
    "Izoh (Qayerga/Kimdan)": "notes_direction",
    "Qarzlar (Liabilities)": "liabilities",
    "Ta'minotchilar": "suppliers",
    "O'tkazmalar": "transfers",
    "Qarzni To'lash (Moliya)": "pay_liability",
    "Pul qaytarib olish": "withdraw_money",
    "Hozircha ta'minotchilar mavjud emas.": "no_suppliers",
    "Kutayotgan Naqdlashtirish So'rovlari": "pending_withdrawals",
    "Qabul qilish": "accept",
    "Ichki O'tkazma / Naqdlashtirish": "internal_transfer_withdrawal",
    "Mijoz / Shartnomaga bog'lash (Ixtiyoriy)": "link_client_contract",
    "O'tkazma Summasi": "transfer_amount",
    "Komissiya (Foyda) UZS": "commission_fee",
    "Qayerdan (Yechiladi)": "from_account",
    "🏦 Bank (Perechislenie)": "bank_account",
    "💰 Naqd Kassa": "cash_account",
    "💳 Plastik (Terminal)": "card_account",
    "Qayerga (Tushadi)": "to_account",
    "Izoh": "notes",
    "O'tkazma Xulosasi": "transfer_summary",
    "To'lov Summasi (UZS)": "payment_amount_uzs",
    "Qaysi Hisobdan Yechiladi?": "which_account_withdraw",
    "Asosiy Kassa (Main)": "main_cash",
    "Bank Hisobi": "bank_account_label",
    "Kredit Kassa": "credit_cash",
    "Qarz Kassa": "liability_cash",
    "Bekor Qilish": "cancel_button",
    "SHER-DOR": "sher_dor",
    "Obyekt:": "object_label",
    "🖨️ CHOP ETISH": "print_button",
    "Shartnoma To'lovini Taqsimlash": "contract_payment_distribution",
    "Yangi Qarz qo'shish": "add_new_liability",
    "Turi": "type_label",
    "Bank Krediti": "bank_credit",
    "Taminotchi Qarzi": "supplier_debt",
    "Hamkor Qarzi": "partner_debt",
    "Kreditor Nomi (Bank yoki Taminotchi)": "creditor_name",
    "Qarz Summasi tushadigan hisob (Majburiy emas)": "liability_receive_account",
    "Umumiy Summa": "total_amount",
    "Yillik Foiz (%)": "annual_percentage",
    "Muddat (Oy)": "term_months",
    "Oylik To'lov (Majburiy emas)": "monthly_payment",
    "Boshlanish Sanasi": "start_date",
    "Tugash Sanasi": "end_date",
    "Qarz To'lovi": "liability_payment",
    "Kreditor:": "creditor_label",
    "Qoldiq:": "balance_remaining_label",
    "To'lov Summasi": "payment_amount_label",
    "Qaysi hisobdan to'lanadi?": "which_account_pay",
    "Sana": "date_label",
    "To'lash": "pay_button",
    "To'lov Grafigi": "payment_schedule_label",
    "Asosiy Qarz": "main_debt",
    "Jami To'lov": "total_payment_label",
    "Qoldiq": "balance_remaining",
    "Yopish": "close_button"
};

function updateLocales() {
    const localesPath = path.join('d:/Sherdor 2.0/resources/js/locales');
    const langs = ['uz', 'ru', 'en'];

    langs.forEach(lang => {
        const file = path.join(localesPath, `${lang}.json`);
        let data = JSON.parse(fs.readFileSync(file, 'utf8'));
        
        for (const [uzbekText, key] of Object.entries(TRANSLATIONS)) {
            if (!data[key]) {
                // If it's uz, keep the uzbek text, otherwise use the key as a placeholder
                data[key] = lang === 'uz' ? uzbekText : `${key}`;
            }
        }
        
        fs.writeFileSync(file, JSON.stringify(data, null, 4));
    });
    console.log("Locales updated!");
}

function replaceInFiles(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            replaceInFiles(fullPath);
        } else if (fullPath.endsWith('.vue')) {
            let content = fs.readFileSync(fullPath, 'utf8');
            let modified = false;

            for (const [uzbekText, key] of Object.entries(TRANSLATIONS)) {
                // Replace >Text< with >{{ $t('key') }}<
                const regex = new RegExp(`>\\s*${uzbekText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*<`, 'g');
                if (regex.test(content)) {
                    content = content.replace(regex, `>{{ $t('${key}') }}<`);
                    modified = true;
                }
                
                // Also look for exact text without brackets if it's the only thing on the line
                // or placeholder="Text"
                const placeholderRegex = new RegExp(`placeholder="${uzbekText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}"`, 'g');
                if (placeholderRegex.test(content)) {
                    content = content.replace(placeholderRegex, `:placeholder="$t('${key}')"`);
                    modified = true;
                }
            }

            if (modified) {
                fs.writeFileSync(fullPath, content);
                console.log(`Updated ${fullPath}`);
            }
        }
    }
}

updateLocales();
replaceInFiles('d:/Sherdor 2.0/resources/js');
