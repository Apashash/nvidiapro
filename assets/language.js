(function () {
    'use strict';

    const STORAGE_KEY = 'gd_language';
    const supported = ['fr', 'en', 'es', 'zh', 'ur'];
    const labels = { fr: 'FR', en: 'EN', es: 'ES', zh: '中', ur: 'اردو' };
    const originalText = new WeakMap();
    const originalAttributes = new WeakMap();
    let originalTitle = null;
    const dictionaries = {
        en: {
            'Accueil': 'Home', 'Investir': 'Invest', 'Salaire': 'Salary', 'Équipe': 'Team', 'Profil': 'Profile',
            'Dépôt': 'Deposit', 'Retrait': 'Withdrawal', 'Historique': 'History', 'Toutes vos transactions': 'All your transactions',
            'Cadeaux': 'Gifts', 'Code cadeau': 'Gift code', 'Machine à Sous': 'Slot Machine',
            'Machine à Sous Gratuite': 'Free Slot Machine', 'Solde Principal': 'Main Balance', 'Revenus Totaux': 'Total Earnings',
            'Bienvenue': 'Welcome', 'Mon Équipe': 'My Team', 'Votre lien de parrainage': 'Your referral link',
            'Plans d’Investissement': 'Investment Plans', "Plans d'Investissement": 'Investment Plans',
            'Actions VIP': 'VIP Shares', 'Mes Commandes': 'My Orders', 'Investir maintenant': 'Invest now',
            'Prix par action': 'Price per share', 'Gain quotidien': 'Daily earnings', 'Gain quotidien possible': 'Possible daily earnings',
            'Montant de départ': 'Starting amount', 'Gain total': 'Total earnings', 'Durée totale': 'Total duration',
            'Préparer votre investissement': 'Prepare your investment', "Nombre d'actions": 'Number of shares',
            'Montant FCFA': 'Amount FCFA', 'Montant USDT': 'Amount USDT', 'Montant souhaité en FCFA': 'Desired amount in FCFA',
            'Montant souhaité en USDT': 'Desired amount in USDT', 'Confirmer l’achat': 'Confirm purchase',
            "Confirmer l'achat": 'Confirm purchase', 'Retour aux plans d’investissement': 'Back to investment plans',
            'Retour aux plans d\'investissement': 'Back to investment plans', 'jours': 'days', 'jour': 'day',
            'Aucune Commande Active': 'No active orders', 'Aucune transaction trouvée': 'No transactions found',
            'Vos dépôts, retraits et revenus s’afficheront ici.': 'Your deposits, withdrawals and earnings will appear here.',
            'Solde disponible': 'Available balance', 'Montant à retirer (FCFA)': 'Withdrawal amount (FCFA)',
            'Montant demandé': 'Requested amount', 'Retrait indisponible': 'Withdrawal unavailable',
            'Retrait envoyé avec succès !': 'Withdrawal submitted successfully!',
            'Votre demande a été soumise. Elle sera traitée dans les 24h.': 'Your request has been submitted. It will be processed within 24 hours.',
            'Connexion': 'Log in', 'Inscription': 'Sign up', 'Mot de passe': 'Password',
            'Confirmer le mot de passe': 'Confirm password', 'Code d’invitation': 'Invitation code',
            'Votre nom complet': 'Your full name', 'Nom complet': 'Full name', 'Se connecter': 'Log in',
            'Créer un compte': 'Create an account', 'Suivant': 'Next', 'Retour': 'Back',
            'Annuler': 'Cancel', 'Recharger': 'Top up', 'Vérifier': 'Verify', 'Validé': 'Validated',
            'En attente': 'Pending', 'Rejeté': 'Rejected', 'Parrainage': 'Referral',
            'Bonus de code': 'Gift code bonus', 'Bonus d’inscription': 'Sign-up bonus',
            'Machine à sous': 'Slot machine', 'Salaire VIP': 'VIP salary', 'Investissement': 'Investment',
            'Revenu': 'Earnings', 'Bonus': 'Bonus', 'Cadeau': 'Gift', 'Enregistrer': 'Save',
            'Aucun Plan Disponible': 'No plan available', 'Bientôt disponible': 'Coming soon',
            'Plan non disponible': 'Plan unavailable', 'J’ai compris': 'Got it', 'Limite atteinte': 'Limit reached',
            'Solde Insuffisant': 'Insufficient balance', 'Traitement...': 'Processing...',
            'Achat effectué avec succès': 'Purchase completed successfully',
            'Achat effectué avec succès !': 'Purchase completed successfully!',
            'Voir mes commandes': 'View my orders', 'Cycle de versement (24h)': 'Payout cycle (24h)',
            'avant versement': 'until payout', 'Versement disponible !': 'Payout available!',
            'Taux': 'Rate', 'Durée': 'Duration', 'Actions': 'Shares', 'Gain': 'Earnings',
            'Tuto': 'Guide', 'Cadeau': 'Gift', 'VIP': 'VIP', 'Rejoindre': 'Join',
            'Retrait bloqué': 'Withdrawal blocked', 'Retrait': 'Withdrawal', 'Dépôt': 'Deposit',
            'Salaire Quotidien': 'Daily Salary', 'Récupérer Mon Salaire': 'Claim My Salary',
            'Salaire déjà reçu pour ce palier': 'Salary already received for this level',
            'Aucune donnée disponible': 'No data available', 'Erreur de connexion.': 'Connection error.',
            'Erreur lors de l’achat': 'Purchase error', 'Choisir la langue': 'Choose language',
            'Français': 'French', 'Anglais': 'English', 'Espagnol': 'Spanish', 'Chinois': 'Chinese',
            'Pakistan': 'Pakistan'
        },
        es: {
            'Accueil': 'Inicio', 'Investir': 'Invertir', 'Salaire': 'Salario', 'Équipe': 'Equipo', 'Profil': 'Perfil',
            'Dépôt': 'Depósito', 'Retrait': 'Retiro', 'Historique': 'Historial', 'Toutes vos transactions': 'Todas tus transacciones',
            'Cadeaux': 'Regalos', 'Code cadeau': 'Código de regalo', 'Machine à Sous': 'Máquina tragamonedas',
            'Machine à Sous Gratuite': 'Máquina tragamonedas gratis', 'Solde Principal': 'Saldo principal', 'Revenus Totaux': 'Ingresos totales',
            'Mon Équipe': 'Mi equipo', 'Votre lien de parrainage': 'Tu enlace de referido',
            'Plans d’Investissement': 'Planes de inversión', "Plans d'Investissement": 'Planes de inversión',
            'Actions VIP': 'Acciones VIP', 'Mes Commandes': 'Mis órdenes', 'Investir maintenant': 'Invertir ahora',
            'Prix par action': 'Precio por acción', 'Gain quotidien': 'Ganancia diaria', 'Gain quotidien possible': 'Ganancia diaria posible',
            'Montant de départ': 'Monto inicial', 'Gain total': 'Ganancia total', 'Durée totale': 'Duración total',
            'Préparer votre investissement': 'Prepara tu inversión', "Nombre d'actions": 'Número de acciones',
            'Montant FCFA': 'Monto FCFA', 'Montant USDT': 'Monto USDT', 'Montant souhaité en FCFA': 'Monto deseado en FCFA',
            'Montant souhaité en USDT': 'Monto deseado en USDT', 'Confirmer l’achat': 'Confirmar compra',
            "Confirmer l'achat": 'Confirmar compra', 'Annuler': 'Cancelar', 'Recharger': 'Recargar',
            'Vérifier': 'Verificar', 'Validé': 'Validado', 'En attente': 'Pendiente', 'Rejeté': 'Rechazado',
            'Parrainage': 'Referido', 'Bonus de code': 'Bono de código', 'Bonus d’inscription': 'Bono de registro',
            'Machine à sous': 'Máquina tragamonedas', 'Salaire VIP': 'Salario VIP', 'Investissement': 'Inversión',
            'Revenu': 'Ingreso', 'Bonus': 'Bono', 'Cadeau': 'Regalo', 'Enregistrer': 'Guardar',
            'Aucune Commande Active': 'No hay órdenes activas', 'Bientôt disponible': 'Próximamente',
            'Plan non disponible': 'Plan no disponible', 'J’ai compris': 'Entendido', 'Limite atteinte': 'Límite alcanzado',
            'Solde Insuffisant': 'Saldo insuficiente', 'Traitement...': 'Procesando...',
            'Achat effectué avec succès': 'Compra realizada con éxito', 'Achat effectué avec succès !': '¡Compra realizada con éxito!',
            'Voir mes commandes': 'Ver mis órdenes', 'Cycle de versement (24h)': 'Ciclo de pago (24h)',
            'avant versement': 'para el pago', 'Versement disponible !': '¡Pago disponible!', 'Tuto': 'Guía',
            'Rejoindre': 'Unirse', 'Retrait bloqué': 'Retiro bloqueado', 'Salaire Quotidien': 'Salario diario',
            'Récupérer Mon Salaire': 'Cobrar mi salario', 'Choisir la langue': 'Elegir idioma',
            'Français': 'Francés', 'Anglais': 'Inglés', 'Espagnol': 'Español', 'Chinois': 'Chino', 'Pakistan': 'Pakistán'
        },
        zh: {
            'Accueil': '首页', 'Investir': '投资', 'Salaire': '工资', 'Équipe': '团队', 'Profil': '个人资料',
            'Dépôt': '充值', 'Retrait': '提现', 'Historique': '交易记录', 'Toutes vos transactions': '所有交易',
            'Cadeaux': '礼品', 'Code cadeau': '礼品码', 'Machine à Sous': '老虎机',
            'Machine à Sous Gratuite': '免费老虎机', 'Solde Principal': '主余额', 'Revenus Totaux': '总收益',
            'Mon Équipe': '我的团队', 'Votre lien de parrainage': '您的推荐链接',
            'Plans d’Investissement': '投资计划', "Plans d'Investissement": '投资计划',
            'Actions VIP': 'VIP股票', 'Mes Commandes': '我的订单', 'Investir maintenant': '立即投资',
            'Prix par action': '每股价格', 'Gain quotidien': '每日收益', 'Gain quotidien possible': '每日可能收益',
            'Montant de départ': '起始金额', 'Gain total': '总收益', 'Durée totale': '总期限',
            'Préparer votre investissement': '准备您的投资', "Nombre d'actions": '股票数量',
            'Montant FCFA': '金额 FCFA', 'Montant USDT': '金额 USDT', 'Montant souhaité en FCFA': '所需金额（FCFA）',
            'Montant souhaité en USDT': '所需金额（USDT）', 'Confirmer l’achat': '确认购买',
            "Confirmer l'achat": '确认购买', 'Annuler': '取消', 'Recharger': '充值', 'Vérifier': '验证',
            'Validé': '已验证', 'En attente': '待处理', 'Rejeté': '已拒绝', 'Parrainage': '推荐',
            'Bonus de code': '代码奖金', 'Bonus d’inscription': '注册奖金', 'Machine à sous': '老虎机',
            'Salaire VIP': 'VIP工资', 'Investissement': '投资', 'Revenu': '收益', 'Bonus': '奖金',
            'Cadeau': '礼品', 'Enregistrer': '保存', 'Aucune Commande Active': '没有活跃订单',
            'Bientôt disponible': '即将推出', 'Plan non disponible': '计划不可用', 'J’ai compris': '知道了',
            'Limite atteinte': '已达上限', 'Solde Insuffisant': '余额不足', 'Traitement...': '处理中...',
            'Achat effectué avec succès': '购买成功', 'Achat effectué avec succès !': '购买成功！',
            'Voir mes commandes': '查看我的订单', 'Cycle de versement (24h)': '支付周期（24小时）',
            'avant versement': '后支付', 'Versement disponible !': '可以支付！', 'Tuto': '教程',
            'Rejoindre': '加入', 'Retrait bloqué': '提现受限', 'Salaire Quotidien': '每日工资',
            'Récupérer Mon Salaire': '领取我的工资', 'Choisir la langue': '选择语言',
            'Français': '法语', 'Anglais': '英语', 'Espagnol': '西班牙语', 'Chinois': '中文', 'Pakistan': '巴基斯坦'
        },
        ur: {
            'Accueil': 'ہوم', 'Investir': 'سرمایہ کاری', 'Salaire': 'تنخواہ', 'Équipe': 'ٹیم', 'Profil': 'پروفائل',
            'Dépôt': 'جمع', 'Retrait': 'رقم نکلوانا', 'Historique': 'تاریخ', 'Toutes vos transactions': 'آپ کے تمام لین دین',
            'Cadeaux': 'تحائف', 'Code cadeau': 'تحفے کا کوڈ', 'Machine à Sous': 'سلاٹ مشین',
            'Machine à Sous Gratuite': 'مفت سلاٹ مشین', 'Solde Principal': 'مرکزی بیلنس', 'Revenus Totaux': 'کل آمدنی',
            'Mon Équipe': 'میری ٹیم', 'Votre lien de parrainage': 'آپ کا ریفرل لنک',
            'Plans d’Investissement': 'سرمایہ کاری کے منصوبے', "Plans d'Investissement": 'سرمایہ کاری کے منصوبے',
            'Actions VIP': 'VIP شیئرز', 'Mes Commandes': 'میرے آرڈرز', 'Investir maintenant': 'ابھی سرمایہ کاری کریں',
            'Prix par action': 'فی شیئر قیمت', 'Gain quotidien': 'روزانہ آمدنی', 'Gain quotidien possible': 'ممکنہ روزانہ آمدنی',
            'Montant de départ': 'ابتدائی رقم', 'Gain total': 'کل آمدنی', 'Durée totale': 'کل مدت',
            'Préparer votre investissement': 'اپنی سرمایہ کاری تیار کریں', "Nombre d'actions": 'شیئرز کی تعداد',
            'Montant FCFA': 'رقم FCFA', 'Montant USDT': 'رقم USDT', 'Montant souhaité en FCFA': 'مطلوبہ رقم FCFA میں',
            'Montant souhaité en USDT': 'مطلوبہ رقم USDT میں', 'Confirmer l’achat': 'خریداری کی تصدیق کریں',
            "Confirmer l'achat": 'خریداری کی تصدیق کریں', 'Annuler': 'منسوخ', 'Recharger': 'رقم جمع کریں',
            'Vérifier': 'تصدیق', 'Validé': 'تصدیق شدہ', 'En attente': 'زیر التوا', 'Rejeté': 'مسترد',
            'Parrainage': 'ریفرل', 'Bonus de code': 'کوڈ بونس', 'Bonus d’inscription': 'رجسٹریشن بونس',
            'Machine à sous': 'سلاٹ مشین', 'Salaire VIP': 'VIP تنخواہ', 'Investissement': 'سرمایہ کاری',
            'Revenu': 'آمدنی', 'Bonus': 'بونس', 'Cadeau': 'تحفہ', 'Enregistrer': 'محفوظ کریں',
            'Aucune Commande Active': 'کوئی فعال آرڈر نہیں', 'Bientôt disponible': 'جلد دستیاب ہوگا',
            'Plan non disponible': 'منصوبہ دستیاب نہیں', 'J’ai compris': 'سمجھ گیا', 'Limite atteinte': 'حد پوری ہوگئی',
            'Solde Insuffisant': 'بیلنس ناکافی ہے', 'Traitement...': 'کارروائی جاری ہے...',
            'Achat effectué avec succès': 'خریداری کامیاب', 'Achat effectué avec succès !': 'خریداری کامیاب!',
            'Voir mes commandes': 'میرے آرڈرز دیکھیں', 'Cycle de versement (24h)': 'ادائیگی کا دورانیہ (24 گھنٹے)',
            'avant versement': 'ادائیگی تک', 'Versement disponible !': 'ادائیگی دستیاب ہے!', 'Tuto': 'رہنما',
            'Rejoindre': 'شامل ہوں', 'Retrait bloqué': 'رقم نکلوانا بند ہے', 'Salaire Quotidien': 'روزانہ تنخواہ',
            'Récupérer Mon Salaire': 'میری تنخواہ حاصل کریں', 'Choisir la langue': 'زبان منتخب کریں',
            'Français': 'فرانسیسی', 'Anglais': 'انگریزی', 'Espagnol': 'ہسپانوی', 'Chinois': 'چینی', 'Pakistan': 'پاکستان'
        }
    };

    function getLanguage() {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        return supported.includes(stored) ? stored : 'fr';
    }

    function normalize(value) {
        return value.replace(/\s+/g, ' ').trim();
    }

    function translateValue(value, language) {
        if (language === 'fr') return value;
        const dictionary = dictionaries[language] || {};
        const normalized = normalize(value);
        if (dictionary[normalized]) return value.replace(normalized, dictionary[normalized]);

        let translated = value;
        Object.keys(dictionary)
            .sort((a, b) => b.length - a.length)
            .forEach((source) => {
                translated = translated.split(source).join(dictionary[source]);
            });
        return translated;
    }

    function shouldSkip(node) {
        const parent = node.parentElement;
        if (!parent) return true;
        return ['SCRIPT', 'STYLE', 'NOSCRIPT', 'CODE', 'PRE'].includes(parent.tagName)
            || parent.closest('[data-language-picker]');
    }

    function translateDom() {
        const language = getLanguage();
        document.documentElement.lang = language;
        document.documentElement.dir = language === 'ur' ? 'rtl' : 'ltr';
        document.querySelectorAll('[data-language-current]').forEach((element) => {
            element.textContent = labels[language];
        });
        document.querySelectorAll('[data-language-option]').forEach((element) => {
            element.classList.toggle('active', element.dataset.languageOption === language);
        });
        if (originalTitle === null) originalTitle = document.title;
        document.title = translateValue(originalTitle, language);

        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach((node) => {
            if (!shouldSkip(node)) {
                if (!originalText.has(node)) originalText.set(node, node.nodeValue);
                const translated = translateValue(originalText.get(node), language);
                if (node.nodeValue !== translated) node.nodeValue = translated;
            }
        });

        document.querySelectorAll('[placeholder], [title], [aria-label]').forEach((element) => {
            ['placeholder', 'title', 'aria-label'].forEach((attribute) => {
                if (element.hasAttribute(attribute)) {
                    if (!originalAttributes.has(element)) originalAttributes.set(element, {});
                    const values = originalAttributes.get(element);
                    if (!(attribute in values)) values[attribute] = element.getAttribute(attribute);
                    const translated = translateValue(values[attribute], language);
                    if (element.getAttribute(attribute) !== translated) {
                        element.setAttribute(attribute, translated);
                    }
                }
            });
        });
    }

    function setLanguage(language) {
        if (!supported.includes(language)) return;
        window.localStorage.setItem(STORAGE_KEY, language);
        translateDom();
        window.dispatchEvent(new CustomEvent('gd-language-change', { detail: { language } }));
    }

    function initializePicker() {
        const picker = document.querySelector('[data-language-picker]');
        if (!picker) return;
        const toggle = picker.querySelector('[data-language-toggle]');
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = picker.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(isOpen));
        });
        picker.querySelectorAll('[data-language-option]').forEach((option) => {
            option.addEventListener('click', () => {
                setLanguage(option.dataset.languageOption);
                picker.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
        document.addEventListener('click', (event) => {
            if (!picker.contains(event.target)) {
                picker.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    window.GDLanguage = { getLanguage, setLanguage, translateDom };
    document.addEventListener('DOMContentLoaded', () => {
        initializePicker();
        translateDom();
        const observer = new MutationObserver(() => translateDom());
        observer.observe(document.body, { childList: true, subtree: true });
    });
})();