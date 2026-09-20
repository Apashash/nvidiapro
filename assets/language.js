(function () {
    'use strict';

    const STORAGE_KEY = 'gd_language';
    const supported = ['fr', 'en', 'es', 'zh', 'ur'];
    const labels = { fr: 'FR', en: 'EN', es: 'ES', zh: '中', ur: 'اردو' };
    const originalText = new WeakMap();
    const originalAttributes = new WeakMap();
    let originalTitle = null;
    const legacyTranslations = {
        'Together, let\'s build the wealth of tomorrow.': {
            en: '“Together, let us build tomorrow’s wealth.”',
            es: '« Juntos, construyamos la riqueza del mañana. »',
            zh: '“让我们共同建设美好的明天。”',
            ur: '“آئیں مل کر کل کی دولت بنائیں۔”'
        },
        Login: { en: 'Log in', es: 'Iniciar sesión', zh: '登录', ur: 'لاگ ان' },
        either: { en: 'or', es: 'o', zh: '或', ur: 'یا' }
    };
    const pageTranslations = {
        'Effectuer un Dépôt': { en: 'Make a deposit', es: 'Realizar un depósito', zh: '进行充值', ur: 'رقم جمع کریں' },
        'Rechargez votre compte Mobile Money': { en: 'Top up your Mobile Money account', es: 'Recarga tu cuenta de Mobile Money', zh: '充值您的移动支付账户', ur: 'اپنے موبائل منی اکاؤنٹ میں رقم جمع کریں' },
        'Code de confirmation requis': { en: 'Confirmation code required', es: 'Código de confirmación requerido', zh: '需要确认码', ur: 'تصدیقی کوڈ درکار ہے' },
        'Composez ce code sur votre téléphone pour recevoir votre OTP :': { en: 'Dial this code on your phone to receive your OTP:', es: 'Marca este código en tu teléfono para recibir tu OTP:', zh: '在手机上拨打此代码以接收验证码：', ur: 'OTP حاصل کرنے کے لیے اپنے فون پر یہ کوڈ ملائیں:' },
        'Un SMS contenant le code vient d\'être envoyé au': { en: 'An SMS containing the code was sent to', es: 'Se envió un SMS con el código a', zh: '包含验证码的短信已发送至', ur: 'کوڈ والا SMS بھیجا گیا ہے:' },
        'Valider le code OTP': { en: 'Verify OTP code', es: 'Validar el código OTP', zh: '验证验证码', ur: 'OTP کوڈ کی تصدیق کریں' },
        'Annuler ce dépôt': { en: 'Cancel this deposit', es: 'Cancelar este depósito', zh: '取消此次充值', ur: 'یہ رقم جمع کرنا منسوخ کریں' },
        'Paiement initié': { en: 'Payment initiated', es: 'Pago iniciado', zh: '付款已发起', ur: 'ادائیگی شروع ہوگئی' },
        'Confirmez sur votre téléphone': { en: 'Confirm on your phone', es: 'Confirma en tu teléfono', zh: '请在手机上确认', ur: 'اپنے فون پر تصدیق کریں' },
        'Payer avec Wave': { en: 'Pay with Wave', es: 'Pagar con Wave', zh: '使用 Wave 支付', ur: 'Wave سے ادائیگی کریں' },
        'Votre solde sera crédité automatiquement dès confirmation.': { en: 'Your balance will be credited automatically once confirmed.', es: 'Tu saldo se acreditará automáticamente al confirmar.', zh: '确认后余额将自动到账。', ur: 'تصدیق کے بعد آپ کا بیلنس خود بخود جمع ہوجائے گا۔' },
        'Montant du dépôt': { en: 'Deposit amount', es: 'Monto del depósito', zh: '充值金额', ur: 'جمع کی جانے والی رقم' },
        'Pays': { en: 'Country', es: 'País', zh: '国家', ur: 'ملک' },
        'Sélectionnez un pays': { en: 'Select a country', es: 'Selecciona un país', zh: '选择国家', ur: 'ملک منتخب کریں' },
        'Opérateur Mobile Money': { en: 'Mobile Money operator', es: 'Operador de Mobile Money', zh: '移动支付运营商', ur: 'موبائل منی آپریٹر' },
        "Sélectionnez d'abord un pays": { en: 'Select a country first', es: 'Selecciona primero un país', zh: '请先选择国家', ur: 'پہلے ملک منتخب کریں' },
        'Sélectionnez un opérateur': { en: 'Select an operator', es: 'Selecciona un operador', zh: '选择运营商', ur: 'آپریٹر منتخب کریں' },
        'Numéro Mobile Money': { en: 'Mobile Money number', es: 'Número de Mobile Money', zh: '移动支付号码', ur: 'موبائل منی نمبر' },
        'Saisissez le numéro sans indicatif (+237…)': { en: 'Enter the number without the country code (+237…)', es: 'Introduce el número sin el prefijo (+237…)', zh: '输入号码时不要包含国家区号（+237…）', ur: 'ملکی کوڈ کے بغیر نمبر درج کریں (+237…)' },
        'Montant minimum :': { en: 'Minimum amount:', es: 'Monto mínimo:', zh: '最低金额：', ur: 'کم از کم رقم:' },
        'Confirmer le paiement': { en: 'Confirm payment', es: 'Confirmar el pago', zh: '确认付款', ur: 'ادائیگی کی تصدیق کریں' },
        'Paiement en cours de traitement…': { en: 'Payment is being processed…', es: 'Pago en proceso…', zh: '付款处理中……', ur: 'ادائیگی پر کارروائی جاری ہے…' },
        'Une notification va apparaître sur votre téléphone': { en: 'A notification will appear on your phone', es: 'Aparecerá una notificación en tu teléfono', zh: '您的手机将显示通知', ur: 'آپ کے فون پر ایک اطلاع ظاہر ہوگی' },
        'Veuillez sélectionner un pays.': { en: 'Please select a country.', es: 'Selecciona un país.', zh: '请选择国家。', ur: 'براہ کرم ملک منتخب کریں۔' },
        'Paiement confirmé !': { en: 'Payment confirmed!', es: '¡Pago confirmado!', zh: '付款已确认！', ur: 'ادائیگی کی تصدیق ہوگئی!' },
        'Votre solde a été crédité automatiquement.': { en: 'Your balance has been credited automatically.', es: 'Tu saldo se ha acreditado automáticamente.', zh: '您的余额已自动到账。', ur: 'آپ کا بیلنس خود بخود جمع ہوگیا ہے۔' },
        'Paiement échoué': { en: 'Payment failed', es: 'Pago fallido', zh: '付款失败', ur: 'ادائیگی ناکام ہوگئی' },
        'La transaction a été rejetée. Veuillez réessayer.': { en: 'The transaction was rejected. Please try again.', es: 'La transacción fue rechazada. Inténtalo de nuevo.', zh: '交易被拒绝，请重试。', ur: 'لین دین مسترد ہوگیا۔ براہ کرم دوبارہ کوشش کریں۔' },
        'En attente de confirmation…': { en: 'Awaiting confirmation…', es: 'Esperando confirmación…', zh: '等待确认……', ur: 'تصدیق کا انتظار ہے…' },
        'Vérifiez votre téléphone. Le solde sera crédité dès validation.': { en: 'Check your phone. The balance will be credited after validation.', es: 'Revisa tu teléfono. El saldo se acreditará después de la validación.', zh: '请查看手机，验证后余额将到账。', ur: 'اپنا فون چیک کریں۔ تصدیق کے بعد بیلنس جمع ہوجائے گا۔' },

        'Retirez votre argent en toute sécurité': { en: 'Withdraw your money securely', es: 'Retira tu dinero de forma segura', zh: '安全提现', ur: 'اپنی رقم محفوظ طریقے سے نکلوائیں' },
        'Solde disponible': { en: 'Available balance', es: 'Saldo disponible', zh: '可用余额', ur: 'دستیاب بیلنس' },
        'Retrait bloqué': { en: 'Withdrawal blocked', es: 'Retiro bloqueado', zh: '提现受限', ur: 'رقم نکلوانا بند ہے' },
        'Pour débloquer votre retrait, vous devez :': { en: 'To unlock your withdrawal, you must:', es: 'Para desbloquear tu retiro, debes:', zh: '要解除提现限制，您必须：', ur: 'رقم نکلوانے کی پابندی ختم کرنے کے لیے آپ کو:' },
        'acheter un nouveau plan VIP': { en: 'buy a new VIP plan', es: 'comprar un nuevo plan VIP', zh: '购买新的 VIP 计划', ur: 'نیا VIP منصوبہ خریدنا ہوگا' },
        'inviter une personne à investir': { en: 'invite someone to invest', es: 'invitar a alguien a invertir', zh: '邀请他人投资', ur: 'کسی شخص کو سرمایہ کاری کی دعوت دینا ہوگی' },
        'Acheter un plan VIP': { en: 'Buy a VIP plan', es: 'Comprar un plan VIP', zh: '购买 VIP 计划', ur: 'VIP منصوبہ خریدیں' },
        'Inviter quelqu\'un': { en: 'Invite someone', es: 'Invitar a alguien', zh: '邀请他人', ur: 'کسی کو مدعو کریں' },
        'Le retrait est indisponible pour le moment, veuillez réessayer plus tard.': { en: 'Withdrawal is currently unavailable. Please try again later.', es: 'El retiro no está disponible por ahora. Inténtalo más tarde.', zh: '提现暂时不可用，请稍后重试。', ur: 'رقم نکلوانا فی الحال دستیاب نہیں، براہ کرم بعد میں دوبارہ کوشش کریں۔' },
        'Vous devez acheter au moins une action avant de pouvoir effectuer un retrait.': { en: 'You must buy at least one share before making a withdrawal.', es: 'Debes comprar al menos una acción antes de retirar.', zh: '提现前您必须至少购买一股。', ur: 'رقم نکلوانے سے پہلے کم از کم ایک شیئر خریدنا ضروری ہے۔' },
        'Informations de retrait': { en: 'Withdrawal information', es: 'Información del retiro', zh: '提现信息', ur: 'رقم نکلوانے کی معلومات' },
        'Nom complet du titulaire': { en: 'Account holder full name', es: 'Nombre completo del titular', zh: '账户持有人全名', ur: 'اکاؤنٹ ہولڈر کا پورا نام' },
        'Nom sur le compte Mobile Money': { en: 'Name on the Mobile Money account', es: 'Nombre de la cuenta de Mobile Money', zh: '移动支付账户姓名', ur: 'موبائل منی اکاؤنٹ پر نام' },
        'Montant à retirer (FCFA)': { en: 'Withdrawal amount (FCFA)', es: 'Monto a retirar (FCFA)', zh: '提现金额（FCFA）', ur: 'نکلوانے کی رقم (FCFA)' },
        'Minimum': { en: 'Minimum', es: 'Mínimo', zh: '最低', ur: 'کم از کم' },
        'Le montant minimum de retrait est de': { en: 'The minimum withdrawal amount is', es: 'El monto mínimo de retiro es de', zh: '最低提现金额为', ur: 'رقم نکلوانے کی کم از کم مقدار ہے' },
        'Montant demandé': { en: 'Requested amount', es: 'Monto solicitado', zh: '申请金额', ur: 'درخواست کردہ رقم' },
        'Frais de retrait (': { en: 'Withdrawal fee (', es: 'Comisión de retiro (', zh: '提现手续费（', ur: 'رقم نکلوانے کی فیس (' },
        'Vous recevrez': { en: 'You will receive', es: 'Recibirás', zh: '您将收到', ur: 'آپ کو موصول ہوں گے' },
        'Demander le retrait': { en: 'Request withdrawal', es: 'Solicitar retiro', zh: '申请提现', ur: 'رقم نکلوانے کی درخواست دیں' },
        'Compris': { en: 'Understood', es: 'Entendido', zh: '知道了', ur: 'سمجھ گیا' },
        'Montant': { en: 'Amount', es: 'Monto', zh: '金额', ur: 'رقم' },
        'Opérateur': { en: 'Operator', es: 'Operador', zh: '运营商', ur: 'آپریٹر' },
        'Numéro': { en: 'Number', es: 'Número', zh: '号码', ur: 'نمبر' },
        'Nom': { en: 'Name', es: 'Nombre', zh: '姓名', ur: 'نام' },
        'OK': { en: 'OK', es: 'OK', zh: '确定', ur: 'ٹھیک ہے' },
        'Retrait envoyé avec succès !': { en: 'Withdrawal submitted successfully!', es: '¡Retiro enviado correctamente!', zh: '提现申请已提交！', ur: 'رقم نکلوانے کی درخواست کامیاب ہوگئی!' },
        'Votre demande a été soumise. Elle sera traitée dans les 24h.': { en: 'Your request has been submitted. It will be processed within 24 hours.', es: 'Tu solicitud ha sido enviada. Se procesará en 24 horas.', zh: '您的申请已提交，将在24小时内处理。', ur: 'آپ کی درخواست جمع ہوگئی ہے۔ اس پر 24 گھنٹوں میں کارروائی ہوگی۔' },
        'Montant minimum non atteint': { en: 'Minimum amount not reached', es: 'No se alcanzó el monto mínimo', zh: '未达到最低金额', ur: 'کم از کم رقم پوری نہیں ہوئی' },
        'Veuillez remplir tous les champs.': { en: 'Please fill in all fields.', es: 'Completa todos los campos.', zh: '请填写所有字段。', ur: 'براہ کرم تمام خانے پُر کریں۔' },
        'Traitement...': { en: 'Processing...', es: 'Procesando...', zh: '处理中……', ur: 'کارروائی جاری ہے...' },
        'Erreur de connexion.': { en: 'Connection error.', es: 'Error de conexión.', zh: '连接错误。', ur: 'کنکشن میں خرابی۔' },

        'Guides pas à pas pour bien démarrer': { en: 'Step-by-step guides to get started', es: 'Guías paso a paso para comenzar', zh: '帮助您快速开始的分步指南', ur: 'آغاز کے لیے مرحلہ وار رہنما' },
        'Comment déposer': { en: 'How to deposit', es: 'Cómo depositar', zh: '如何充值', ur: 'رقم کیسے جمع کریں' },
        'Rechargez votre compte via Mobile Money pour commencer à investir.': { en: 'Top up your account via Mobile Money to start investing.', es: 'Recarga tu cuenta mediante Mobile Money para empezar a invertir.', zh: '通过移动支付充值账户，开始投资。', ur: 'سرمایہ کاری شروع کرنے کے لیے موبائل منی سے اپنے اکاؤنٹ میں رقم جمع کریں۔' },
        'Tutoriel Telegram': { en: 'Telegram tutorial', es: 'Tutorial de Telegram', zh: 'Telegram 教程', ur: 'ٹیلیگرام رہنما' },
        'Pour les utilisateurs Cameroun': { en: 'For users in Cameroon', es: 'Para usuarios de Camerún', zh: '适用于喀麦隆用户', ur: 'کیمرون کے صارفین کے لیے' },
        'Pour les autres pays': { en: 'For other countries', es: 'Para otros países', zh: '适用于其他国家', ur: 'دوسرے ممالک کے لیے' },
        'Allez dans Dépôt': { en: 'Go to Deposit', es: 'Ve a Depósito', zh: '进入充值页面', ur: 'رقم جمع کرنے کے صفحے پر جائیں' },
        'Cliquez sur "Dépôt" dans le menu de navigation.': { en: 'Click "Deposit" in the navigation menu.', es: 'Haz clic en "Depósito" en el menú de navegación.', zh: '点击导航菜单中的“充值”。', ur: 'نیویگیشن مینو میں "رقم جمع" پر کلک کریں۔' },
        'Choisissez votre pays et opérateur': { en: 'Choose your country and operator', es: 'Elige tu país y operador', zh: '选择您的国家和运营商', ur: 'اپنا ملک اور آپریٹر منتخب کریں' },
        'Sélectionnez votre pays et l\'opérateur Mobile Money que vous utilisez.': { en: 'Select your country and the Mobile Money operator you use.', es: 'Selecciona tu país y el operador de Mobile Money que utilizas.', zh: '选择您的国家和所使用的移动支付运营商。', ur: 'اپنا ملک اور استعمال ہونے والا موبائل منی آپریٹر منتخب کریں۔' },
        'Entrez le montant et votre numéro': { en: 'Enter the amount and your number', es: 'Introduce el monto y tu número', zh: '输入金额和号码', ur: 'رقم اور اپنا نمبر درج کریں' },
        'Saisissez votre numéro Mobile Money.': { en: 'Enter your Mobile Money number.', es: 'Introduce tu número de Mobile Money.', zh: '输入您的移动支付号码。', ur: 'اپنا موبائل منی نمبر درج کریں۔' },
        'Vous recevrez une notification. Entrez votre PIN Mobile Money pour confirmer.': { en: 'You will receive a notification. Enter your Mobile Money PIN to confirm.', es: 'Recibirás una notificación. Introduce tu PIN de Mobile Money para confirmar.', zh: '您将收到通知，请输入移动支付 PIN 进行确认。', ur: 'آپ کو اطلاع ملے گی۔ تصدیق کے لیے اپنا موبائل منی PIN درج کریں۔' },
        'Crédit automatique': { en: 'Automatic credit', es: 'Acreditación automática', zh: '自动到账', ur: 'خودکار کریڈٹ' },
        'Votre solde est mis à jour automatiquement après validation du paiement.': { en: 'Your balance is updated automatically after payment validation.', es: 'Tu saldo se actualiza automáticamente tras validar el pago.', zh: '付款验证后您的余额会自动更新。', ur: 'ادائیگی کی تصدیق کے بعد آپ کا بیلنس خود بخود اپ ڈیٹ ہوجاتا ہے۔' },
        'Comment retirer': { en: 'How to withdraw', es: 'Cómo retirar', zh: '如何提现', ur: 'رقم کیسے نکلوائیں' },
        'Retirez vos gains sur votre compte Mobile Money.': { en: 'Withdraw your earnings to your Mobile Money account.', es: 'Retira tus ganancias a tu cuenta de Mobile Money.', zh: '将收益提现到您的移动支付账户。', ur: 'اپنی آمدنی اپنے موبائل منی اکاؤنٹ میں نکلوائیں۔' },
        'Conditions :': { en: 'Conditions:', es: 'Condiciones:', zh: '条件：', ur: 'شرائط:' },
        'Vous devez avoir au moins un plan d\'investissement actif.': { en: 'You must have at least one active investment plan.', es: 'Debes tener al menos un plan de inversión activo.', zh: '您必须至少拥有一个有效的投资计划。', ur: 'آپ کے پاس کم از کم ایک فعال سرمایہ کاری کا منصوبہ ہونا چاہیے۔' },
        'Retraits disponibles :': { en: 'Withdrawals available:', es: 'Retiros disponibles:', zh: '提现时间：', ur: 'رقم نکلوانے کا وقت:' },
        'Remplissez le formulaire': { en: 'Fill in the form', es: 'Completa el formulario', zh: '填写表单', ur: 'فارم پُر کریں' },
        'Traitement sous 24h': { en: 'Processed within 24 hours', es: 'Procesamiento en 24 h', zh: '24小时内处理', ur: '24 گھنٹوں میں کارروائی' },
        'Comment investir': { en: 'How to invest', es: 'Cómo invertir', zh: '如何投资', ur: 'سرمایہ کاری کیسے کریں' },
        'Choisissez un plan et commencez à générer des revenus passifs.': { en: 'Choose a plan and start generating passive income.', es: 'Elige un plan y empieza a generar ingresos pasivos.', zh: '选择计划并开始获得被动收入。', ur: 'ایک منصوبہ منتخب کریں اور غیر فعال آمدنی حاصل کرنا شروع کریں۔' },
        'Tutoriel Investissement': { en: 'Investment tutorial', es: 'Tutorial de inversión', zh: '投资教程', ur: 'سرمایہ کاری کا رہنما' },
        'Voir la vidéo complète sur Telegram': { en: 'Watch the full video on Telegram', es: 'Mira el vídeo completo en Telegram', zh: '在 Telegram 上观看完整视频', ur: 'ٹیلیگرام پر مکمل ویڈیو دیکھیں' },
        'Rechargez votre solde': { en: 'Top up your balance', es: 'Recarga tu saldo', zh: '充值余额', ur: 'اپنا بیلنس جمع کریں' },
        'Choisissez un plan': { en: 'Choose a plan', es: 'Elige un plan', zh: '选择计划', ur: 'منصوبہ منتخب کریں' },
        'Choisissez le nombre ou le montant': { en: 'Choose the quantity or amount', es: 'Elige la cantidad o el monto', zh: '选择数量或金额', ur: 'تعداد یا رقم منتخب کریں' },
        'Vérifiez le taux de votre tranche': { en: 'Check the rate for your tier', es: 'Comprueba la tasa de tu tramo', zh: '查看您所在档位的利率', ur: 'اپنے درجے کی شرح دیکھیں' },
        'Recevez vos revenus': { en: 'Receive your earnings', es: 'Recibe tus ganancias', zh: '领取您的收益', ur: 'اپنی آمدنی حاصل کریں' },
        'Astuces & Conseils': { en: 'Tips & Advice', es: 'Consejos', zh: '技巧与建议', ur: 'تجاویز اور مشورے' },
        'Maximisez vos gains avec ces conseils.': { en: 'Maximize your earnings with these tips.', es: 'Maximiza tus ganancias con estos consejos.', zh: '用这些建议最大化您的收益。', ur: 'ان تجاویز سے اپنی آمدنی زیادہ کریں۔' },
        'Parrainage :': { en: 'Referral:', es: 'Referidos:', zh: '推荐：', ur: 'ریفرل:' },
        'Machine à sous :': { en: 'Slot machine:', es: 'Máquina tragamonedas:', zh: '老虎机：', ur: 'سلاٹ مشین:' },
        'Niveaux VIP :': { en: 'VIP levels:', es: 'Niveles VIP:', zh: 'VIP 等级：', ur: 'VIP سطحیں:' },
        'Réinvestir :': { en: 'Reinvest:', es: 'Reinvertir:', zh: '再投资：', ur: 'دوبارہ سرمایہ کاری:' },

        'Foire aux questions — réponses rapides': { en: 'Frequently asked questions — quick answers', es: 'Preguntas frecuentes — respuestas rápidas', zh: '常见问题——快速解答', ur: 'اکثر پوچھے گئے سوالات — فوری جوابات' },
        'Présentation générale': { en: 'General overview', es: 'Presentación general', zh: '概况介绍', ur: 'عمومی تعارف' },
        "C'est quoi Groupe Dangote (GD) ?": { en: 'What is Groupe Dangote (GD)?', es: '¿Qué es Groupe Dangote (GD)?', zh: 'Groupe Dangote（GD）是什么？', ur: 'Groupe Dangote (GD) کیا ہے؟' },
        'Dans quels secteurs le groupe Dangote est-il actif ?': { en: 'Which sectors is Dangote Group active in?', es: '¿En qué sectores opera el grupo Dangote?', zh: 'Dangote 集团涉足哪些行业？', ur: 'ڈانگوٹے گروپ کن شعبوں میں فعال ہے؟' },
        'Comment faut-il comprendre le capital du groupe ?': { en: 'How should the group’s capital be understood?', es: '¿Cómo debe entenderse el capital del grupo?', zh: '应如何理解集团的资本？', ur: 'گروپ کے سرمائے کو کیسے سمجھنا چاہیے؟' },
        'Quel est le modèle industriel de Dangote ?': { en: 'What is Dangote’s industrial model?', es: '¿Cuál es el modelo industrial de Dangote?', zh: 'Dangote 的工业模式是什么？', ur: 'ڈانگوٹے کا صنعتی ماڈل کیا ہے؟' },
        "Plans d'investissement": { en: 'Investment plans', es: 'Planes de inversión', zh: '投资计划', ur: 'سرمایہ کاری کے منصوبے' },
        'Comment fonctionne un investissement, de A à Z ?': { en: 'How does an investment work, from A to Z?', es: '¿Cómo funciona una inversión, de principio a fin?', zh: '投资如何运作？', ur: 'سرمایہ کاری A سے Z تک کیسے کام کرتی ہے؟' },
        'Quelles sont les tranches de gain quotidien ?': { en: 'What are the daily earnings tiers?', es: '¿Cuáles son los tramos de ganancias diarias?', zh: '每日收益有哪些档位？', ur: 'روزانہ آمدنی کے درجے کیا ہیں؟' },
        'Quel est le montant minimum pour investir ?': { en: 'What is the minimum investment amount?', es: '¿Cuál es el monto mínimo para invertir?', zh: '最低投资金额是多少？', ur: 'سرمایہ کاری کی کم از کم رقم کتنی ہے؟' },
        'Quels sont les plans disponibles ?': { en: 'Which plans are available?', es: '¿Qué planes están disponibles?', zh: '有哪些可用计划？', ur: 'کون سے منصوبے دستیاب ہیں؟' },
        "Dépôts & Retraits": { en: 'Deposits & Withdrawals', es: 'Depósitos y retiros', zh: '充值与提现', ur: 'رقم جمع اور نکلوانا' },
        'Comment effectuer un dépôt ?': { en: 'How do I make a deposit?', es: '¿Cómo hago un depósito?', zh: '如何充值？', ur: 'رقم کیسے جمع کروں؟' },
        'Quand puis-je faire un retrait ?': { en: 'When can I make a withdrawal?', es: '¿Cuándo puedo retirar?', zh: '什么时候可以提现？', ur: 'میں رقم کب نکلو ا سکتا ہوں؟' },
        'Combien de temps prend un retrait ?': { en: 'How long does a withdrawal take?', es: '¿Cuánto tarda un retiro?', zh: '提现需要多长时间？', ur: 'رقم نکلوانے میں کتنا وقت لگتا ہے؟' },
        "Programme d'ambassadeurs": { en: 'Ambassador program', es: 'Programa de embajadores', zh: '大使计划', ur: 'سفیر پروگرام' },
        "Comment fonctionne le programme d'ambassadeurs ?": { en: 'How does the ambassador program work?', es: '¿Cómo funciona el programa de embajadores?', zh: '大使计划如何运作？', ur: 'سفیر پروگرام کیسے کام کرتا ہے؟' },
        'Sécurité & confiance': { en: 'Security & trust', es: 'Seguridad y confianza', zh: '安全与信任', ur: 'سیکیورٹی اور اعتماد' },
        "Où vont mes fonds lors d'un retrait ?": { en: 'Where do my funds go when I withdraw?', es: '¿Adónde van mis fondos al retirar?', zh: '提现时资金会转到哪里？', ur: 'رقم نکلوانے پر میری رقم کہاں جاتی ہے؟' },
        'Quelles sont les valeurs de Groupe Dangote (GD) ?': { en: 'What are Groupe Dangote (GD)’s values?', es: '¿Cuáles son los valores de Groupe Dangote (GD)?', zh: 'Groupe Dangote（GD）的价值观是什么？', ur: 'Groupe Dangote (GD) کی اقدار کیا ہیں؟' },
        'Rejoindre la communauté': { en: 'Join the community', es: 'Únete a la comunidad', zh: '加入社区', ur: 'کمیونٹی میں شامل ہوں' },
        'Canal Telegram officiel': { en: 'Official Telegram channel', es: 'Canal oficial de Telegram', zh: '官方 Telegram 频道', ur: 'سرکاری ٹیلیگرام چینل' },
        'Annonces, actualités et offres exclusives': { en: 'Announcements, news and exclusive offers', es: 'Anuncios, noticias y ofertas exclusivas', zh: '公告、新闻和独家优惠', ur: 'اعلانات، خبریں اور خصوصی پیشکشیں' },
        'Échangez avec la communauté GD': { en: 'Chat with the GD community', es: 'Habla con la comunidad GD', zh: '与 GD 社区交流', ur: 'GD کمیونٹی سے بات کریں' }
    };
    const dictionaries = {
        en: {
            'Accueil': 'Home', 'Investir': 'Invest', 'Salaire': 'Salary', 'Équipe': 'Team', 'Profil': 'Profile',
            'Dépôt': 'Deposit', 'Retrait': 'Withdrawal', 'Historique': 'History', 'Toutes vos transactions': 'All your transactions',
            'Bienvenue !': 'Welcome!', 'Connectez-vous à votre compte': 'Log in to your account',
            'Numéro de Téléphone': 'Phone number', 'Numéro de téléphone': 'Phone number',
            'Mot de passe': 'Password', 'Votre mot de passe': 'Your password',
            'Se Connecter': 'Log in', 'Créer un compte': 'Create an account', 'ou': 'or',
            'Rejoignez Groupe Dangote': 'Join Dangote Group', 'Code d’invitation': 'Invitation code',
            'Nom complet': 'Full name', 'Votre nom complet': 'Your full name', 'Numéro WhatsApp': 'WhatsApp number',
            'Autre': 'Other', 'Indicatif personnalisé': 'Custom calling code', 'Aucun pays trouvé.': 'No country found.',
            'Indicatif de pays (ex. +221)': 'Country calling code (e.g. +221)',
            'Choisissez un mot de passe': 'Choose a password', 'Confirmer le mot de passe': 'Confirm password',
            'Confirmez le mot de passe': 'Confirm your password', 'Créer mon compte': 'Create my account',
            'Déjà membre ?': 'Already a member?', 'Se connecter': 'Log in',
            'Vous êtes invité par un ambassadeur Groupe Dangote (GD)': 'You were invited by a Groupe Dangote (GD) ambassador',
            'Commandes': 'Orders', 'Actions VIP': 'VIP Shares', 'Prix / action': 'Price / share',
            'Minimum :': 'Minimum:', 'Jours': 'Days', 'achat(s)': 'purchase(s)', 'Investir': 'Invest',
            'Montant investi :': 'Invested amount:', 'actions': 'shares', 'Historique des Transactions': 'Transaction history',
            'Tutoriels': 'Tutorials', 'Groupe Telegram': 'Telegram group', 'Panneau Admin': 'Admin panel',
            'Déconnexion': 'Log out', 'Êtes-vous sûr de vouloir vous déconnecter ?': 'Are you sure you want to log out?',
            'Oui, quitter': 'Yes, log out', 'Devise': 'Currency', 'Niveau max !': 'Maximum level!',
            'Plan actif': 'Active plan', 'filleuls': 'referrals', 'Encore': 'Still', 'pour VIP': 'for VIP',
            'Notre industrie au service du développement': 'Our industry serving development',
            'Nos équipes': 'Our teams', 'Des femmes et des hommes engagés': 'Committed women and men',
            'Nos installations': 'Our facilities', 'Une industrie moderne et ambitieuse': 'A modern and ambitious industry',
            'Notre capacité': 'Our capacity', 'Des infrastructures conçues pour durer': 'Infrastructure built to last',
            'L’avenir industriel': 'The industrial future', "L'avenir industriel": 'The industrial future',
            'Construire une croissance durable en Afrique': 'Building sustainable growth in Africa',
            'Ensemble, construisons la richesse de demain': 'Together, let us build tomorrow’s wealth',
            'Slot': 'Slot machine', 'Actualités Communauté': 'Community news', 'Poster': 'Post',
            'Aucun post pour le moment': 'No posts yet', 'Plans Actions VIP': 'VIP share plans',
            'Taux quotidien': 'Daily rate', 'Gain / jour': 'Earnings / day', 'Voir tous les plans': 'View all plans',
            'Bienvenue sur Groupe Dangote (GD) !': 'Welcome to Groupe Dangote (GD)!',
            'Canal Telegram': 'Telegram channel', 'Groupe WhatsApp': 'WhatsApp group', 'Fermer': 'Close',
            'Rejoignez notre Communauté': 'Join our community',
            'Restez connecté et ne manquez aucune opportunité d’investissement !': 'Stay connected and do not miss any investment opportunity!',
            'Recevez votre rémunération selon votre niveau VIP': 'Receive your earnings according to your VIP level',
            'NIVEAU VIP': 'VIP LEVEL', 'Versement unique à l’atteinte du palier': 'One-time payment when the tier is reached',
            "Versement unique à l'atteinte du palier": 'One-time payment when the tier is reached',
            'Débloquez votre salaire': 'Unlock your salary', 'Progression par palier VIP': 'VIP tier progress',
            'Aucun palier configuré.': 'No tiers configured.', 'Filleuls actifs': 'Active referrals',
            'filleul(s) actif(s) requis': 'active referral(s) required', 'filleul(s)': 'referral(s)',
            'Salaire déjà versé (une seule fois par palier)': 'Salary already paid (once per tier)',
            'Débloqué — salaire disponible !': 'Unlocked — salary available!',
            'En cours — encore': 'In progress —', 'Verrouillé —': 'Locked —', 'Inviter': 'Invite',
            'SALAIRE REÇU !': 'SALARY RECEIVED!', 'Félicitations !': 'Congratulations!',
            'ont été versés dans votre portefeuille.': 'has been added to your wallet.',
            'Ce palier est maintenant soldé — invitez plus de filleuls actifs pour débloquer le prochain palier VIP et son salaire.': 'This tier is now completed — invite more active referrals to unlock the next VIP tier and its salary.',
            'Super, merci !': 'Great, thank you!',
            'Cameroun': 'Cameroon', 'Rechercher un pays': 'Search for a country',
            '« Ensemble, construisons la richesse de demain. »': '“Together, let us build tomorrow’s wealth.”',
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
            'Pakistan': 'Pakistan',
            'Gérez vos filleuls et suivez vos gains de parrainage': 'Manage your referrals and track your referral earnings',
            'Copier': 'Copy', 'Nombre de personnes': 'Number of people', 'Total commission': 'Total commission',
            'Les commissions sont calculées sur chaque dépôt validé de vos filleuls :': 'Commissions are calculated on each validated deposit from your referrals:',
            'au niveau 1,': 'at level 1,', 'au niveau 2 et': 'at level 2, and', 'au niveau 3.': 'at level 3.',
            'Un filleul doit acheter au moins une action pour effectuer un retrait.': 'A referral must purchase at least one share to make a withdrawal.',
            'Nv': 'Lvl', 'Niveau': 'Level', 'membre(s)': 'member(s)',
            'Équipe niveau 1': 'Level 1 team', 'Équipe niveau 2': 'Level 2 team', 'Équipe niveau 3': 'Level 3 team',
            'pour le niveau suivant': 'to reach the next level', 'Niveau maximum atteint 🎉': 'Maximum level reached 🎉',
            'Aucun filleul au niveau 1.<br>Partagez votre lien !': 'No level 1 referrals.<br>Share your link!',
            'Aucun filleul au niveau 2': 'No level 2 referrals', 'Aucun filleul au niveau 3': 'No level 3 referrals',
            'Actif': 'Active', 'Inactif': 'Inactive', 'Page': 'Page', 'Préc.': 'Prev.', 'Suiv.': 'Next',
            'Lien copié !': 'Link copied!', 'Rejoins-moi sur Groupe Dangote (GD) !': 'Join me on Groupe Dangote (GD)!'
        },
        es: {
            'Accueil': 'Inicio', 'Investir': 'Invertir', 'Salaire': 'Salario', 'Équipe': 'Equipo', 'Profil': 'Perfil',
            'Dépôt': 'Depósito', 'Retrait': 'Retiro', 'Historique': 'Historial', 'Toutes vos transactions': 'Todas tus transacciones',
            'Bienvenue !': '¡Bienvenido!', 'Connectez-vous à votre compte': 'Inicia sesión en tu cuenta',
            'Numéro de Téléphone': 'Número de teléfono', 'Numéro de téléphone': 'Número de teléfono',
            'Mot de passe': 'Contraseña', 'Votre mot de passe': 'Tu contraseña',
            'Se Connecter': 'Iniciar sesión', 'Créer un compte': 'Crear una cuenta', 'ou': 'o',
            'Rejoignez Groupe Dangote': 'Únete a Grupo Dangote', 'Code d’invitation': 'Código de invitación',
            'Nom complet': 'Nombre completo', 'Votre nom complet': 'Tu nombre completo', 'Numéro WhatsApp': 'Número de WhatsApp',
            'Autre': 'Otro', 'Indicatif personnalisé': 'Indicativo personalizado', 'Aucun pays trouvé.': 'No se encontró ningún país.',
            'Indicatif de pays (ex. +221)': 'Indicativo de país (ej. +221)',
            'Choisissez un mot de passe': 'Elige una contraseña', 'Confirmer le mot de passe': 'Confirmar contraseña',
            'Confirmez le mot de passe': 'Confirma tu contraseña', 'Créer mon compte': 'Crear mi cuenta',
            'Déjà membre ?': '¿Ya eres miembro?', 'Se connecter': 'Iniciar sesión',
            'Vous êtes invité par un ambassadeur Groupe Dangote (GD)': 'Has sido invitado por un embajador de Grupo Dangote (GD)',
            'Commandes': 'Órdenes', 'Actions VIP': 'Acciones VIP', 'Prix / action': 'Precio / acción',
            'Minimum :': 'Mínimo:', 'Jours': 'Días', 'achat(s)': 'compra(s)', 'Investir': 'Invertir',
            'Montant investi :': 'Monto invertido:', 'actions': 'acciones', 'Historique des Transactions': 'Historial de transacciones',
            'Tutoriels': 'Tutoriales', 'Groupe Telegram': 'Grupo de Telegram', 'Panneau Admin': 'Panel de administración',
            'Déconnexion': 'Cerrar sesión', 'Êtes-vous sûr de vouloir vous déconnecter ?': '¿Seguro que quieres cerrar sesión?',
            'Oui, quitter': 'Sí, cerrar sesión', 'Devise': 'Moneda', 'Niveau max !': '¡Nivel máximo!',
            'Plan actif': 'Plan activo', 'filleuls': 'referidos', 'Encore': 'Aún', 'pour VIP': 'para VIP',
            'Notre industrie au service du développement': 'Nuestra industria al servicio del desarrollo',
            'Nos équipes': 'Nuestros equipos', 'Des femmes et des hommes engagés': 'Mujeres y hombres comprometidos',
            'Nos installations': 'Nuestras instalaciones', 'Une industrie moderne et ambitieuse': 'Una industria moderna y ambiciosa',
            'Notre capacité': 'Nuestra capacidad', 'Des infrastructures conçues pour durer': 'Infraestructuras construidas para durar',
            'L’avenir industriel': 'El futuro industrial', "L'avenir industriel": 'El futuro industrial',
            'Construire une croissance durable en Afrique': 'Construir un crecimiento sostenible en África',
            'Ensemble, construisons la richesse de demain': 'Juntos, construyamos la riqueza del mañana',
            'Slot': 'Máquina tragamonedas', 'Actualités Communauté': 'Noticias de la comunidad', 'Poster': 'Publicar',
            'Aucun post pour le moment': 'No hay publicaciones todavía', 'Plans Actions VIP': 'Planes de acciones VIP',
            'Taux quotidien': 'Tasa diaria', 'Gain / jour': 'Ganancia / día', 'Voir tous les plans': 'Ver todos los planes',
            'Bienvenue sur Groupe Dangote (GD) !': '¡Bienvenido a Groupe Dangote (GD)!',
            'Canal Telegram': 'Canal de Telegram', 'Groupe WhatsApp': 'Grupo de WhatsApp', 'Fermer': 'Cerrar',
            'Rejoignez notre Communauté': 'Únete a nuestra comunidad',
            'Restez connecté et ne manquez aucune opportunité d’investissement !': '¡Mantente conectado y no pierdas ninguna oportunidad de inversión!',
            'Recevez votre rémunération selon votre niveau VIP': 'Recibe tus ganancias según tu nivel VIP',
            'NIVEAU VIP': 'NIVEL VIP', 'Versement unique à l’atteinte du palier': 'Pago único al alcanzar el nivel',
            "Versement unique à l'atteinte du palier": 'Pago único al alcanzar el nivel',
            'Débloquez votre salaire': 'Desbloquea tu salario', 'Progression par palier VIP': 'Progreso por nivel VIP',
            'Aucun palier configuré.': 'No hay niveles configurados.', 'Filleuls actifs': 'Referidos activos',
            'filleul(s) actif(s) requis': 'referido(s) activo(s) requerido(s)', 'filleul(s)': 'referido(s)',
            'Salaire déjà versé (une seule fois par palier)': 'Salario ya pagado (una vez por nivel)',
            'Débloqué — salaire disponible !': 'Desbloqueado — ¡salario disponible!',
            'En cours — encore': 'En curso —', 'Verrouillé —': 'Bloqueado —', 'Inviter': 'Invitar',
            'SALAIRE REÇU !': '¡SALARIO RECIBIDO!', 'Félicitations !': '¡Felicidades!',
            'ont été versés dans votre portefeuille.': 'se han abonado en tu billetera.',
            'Ce palier est maintenant soldé — invitez plus de filleuls actifs pour débloquer le prochain palier VIP et son salaire.': 'Este nivel está completado — invita más referidos activos para desbloquear el siguiente nivel VIP y su salario.',
            'Super, merci !': '¡Genial, gracias!',
            'Cameroun': 'Camerún', 'Rechercher un pays': 'Buscar un país',
            '« Ensemble, construisons la richesse de demain. »': '« Juntos, construyamos la riqueza del mañana. »',
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
            'Français': 'Francés', 'Anglais': 'Inglés', 'Espagnol': 'Español', 'Chinois': 'Chino', 'Pakistan': 'Pakistán',
            'Gérez vos filleuls et suivez vos gains de parrainage': 'Gestiona tus referidos y sigue tus ganancias por referidos',
            'Copier': 'Copiar', 'Nombre de personnes': 'Número de personas', 'Total commission': 'Comisión total',
            'Les commissions sont calculées sur chaque dépôt validé de vos filleuls :': 'Las comisiones se calculan sobre cada depósito validado de tus referidos:',
            'au niveau 1,': 'en el nivel 1,', 'au niveau 2 et': 'en el nivel 2 y', 'au niveau 3.': 'en el nivel 3.',
            'Un filleul doit acheter au moins une action pour effectuer un retrait.': 'Un referido debe comprar al menos una acción para realizar un retiro.',
            'Nv': 'Niv.', 'Niveau': 'Nivel', 'membre(s)': 'miembro(s)',
            'Équipe niveau 1': 'Equipo de nivel 1', 'Équipe niveau 2': 'Equipo de nivel 2', 'Équipe niveau 3': 'Equipo de nivel 3',
            'pour le niveau suivant': 'para alcanzar el siguiente nivel', 'Niveau maximum atteint 🎉': '¡Nivel máximo alcanzado! 🎉',
            'Aucun filleul au niveau 1.<br>Partagez votre lien !': 'No hay referidos de nivel 1.<br>¡Comparte tu enlace!',
            'Aucun filleul au niveau 2': 'No hay referidos de nivel 2', 'Aucun filleul au niveau 3': 'No hay referidos de nivel 3',
            'Actif': 'Activo', 'Inactif': 'Inactivo', 'Page': 'Página', 'Préc.': 'Ant.', 'Suiv.': 'Sig.',
            'Lien copié !': '¡Enlace copiado!', 'Rejoins-moi sur Groupe Dangote (GD) !': '¡Únete a mí en Groupe Dangote (GD)!'
        },
        zh: {
            'Accueil': '首页', 'Investir': '投资', 'Salaire': '工资', 'Équipe': '团队', 'Profil': '个人资料',
            'Dépôt': '充值', 'Retrait': '提现', 'Historique': '交易记录', 'Toutes vos transactions': '所有交易',
            'Bienvenue !': '欢迎！', 'Connectez-vous à votre compte': '登录您的账户',
            'Numéro de Téléphone': '电话号码', 'Numéro de téléphone': '电话号码',
            'Mot de passe': '密码', 'Votre mot de passe': '您的密码',
            'Se Connecter': '登录', 'Créer un compte': '创建账户', 'ou': '或',
            'Rejoignez Groupe Dangote': '加入Dangote集团', 'Code d’invitation': '邀请码',
            'Nom complet': '姓名', 'Votre nom complet': '您的姓名', 'Numéro WhatsApp': 'WhatsApp号码',
            'Autre': '其他', 'Indicatif personnalisé': '自定义区号', 'Aucun pays trouvé.': '未找到国家。',
            'Indicatif de pays (ex. +221)': '国家区号（例如 +221）',
            'Choisissez un mot de passe': '设置密码', 'Confirmer le mot de passe': '确认密码',
            'Confirmez le mot de passe': '确认您的密码', 'Créer mon compte': '创建我的账户',
            'Déjà membre ?': '已经是会员？', 'Se connecter': '登录',
            'Vous êtes invité par un ambassadeur Groupe Dangote (GD)': '您受Dangote集团（GD）大使邀请',
            'Commandes': '订单', 'Actions VIP': 'VIP股票', 'Prix / action': '每股价格',
            'Minimum :': '最低金额：', 'Jours': '天', 'achat(s)': '次购买', 'Investir': '投资',
            'Montant investi :': '投资金额：', 'actions': '股票', 'Historique des Transactions': '交易历史',
            'Tutoriels': '教程', 'Groupe Telegram': 'Telegram群组', 'Panneau Admin': '管理面板',
            'Déconnexion': '退出登录', 'Êtes-vous sûr de vouloir vous déconnecter ?': '确定要退出登录吗？',
            'Oui, quitter': '是，退出', 'Devise': '货币', 'Niveau max !': '最高等级！',
            'Plan actif': '有效计划', 'filleuls': '推荐人', 'Encore': '还需', 'pour VIP': '个即可达到 VIP',
            'Notre industrie au service du développement': '我们的工业服务于发展',
            'Nos équipes': '我们的团队', 'Des femmes et des hommes engagés': '敬业的女性和男性',
            'Nos installations': '我们的设施', 'Une industrie moderne et ambitieuse': '现代而有抱负的工业',
            'Notre capacité': '我们的能力', 'Des infrastructures conçues pour durer': '经久耐用的基础设施',
            'L’avenir industriel': '工业的未来', "L'avenir industriel": '工业的未来',
            'Construire une croissance durable en Afrique': '在非洲建设可持续增长',
            'Ensemble, construisons la richesse de demain': '让我们共同建设美好的明天',
            'Slot': '老虎机', 'Actualités Communauté': '社区动态', 'Poster': '发布',
            'Aucun post pour le moment': '目前没有帖子', 'Plans Actions VIP': 'VIP股票计划',
            'Taux quotidien': '每日利率', 'Gain / jour': '每日收益', 'Voir tous les plans': '查看所有计划',
            'Bienvenue sur Groupe Dangote (GD) !': '欢迎来到Groupe Dangote（GD）！',
            'Canal Telegram': 'Telegram频道', 'Groupe WhatsApp': 'WhatsApp群组', 'Fermer': '关闭',
            'Rejoignez notre Communauté': '加入我们的社区',
            'Restez connecté et ne manquez aucune opportunité d’investissement !': '保持联系，不要错过任何投资机会！',
            'Recevez votre rémunération selon votre niveau VIP': '根据您的VIP等级获得收益',
            'NIVEAU VIP': 'VIP等级', 'Versement unique à l’atteinte du palier': '达到等级后一次性支付',
            "Versement unique à l'atteinte du palier": '达到等级后一次性支付',
            'Débloquez votre salaire': '解锁您的工资', 'Progression par palier VIP': 'VIP等级进度',
            'Aucun palier configuré.': '尚未配置等级。', 'Filleuls actifs': '活跃推荐人',
            'filleul(s) actif(s) requis': '所需活跃推荐人', 'filleul(s)': '推荐人',
            'Salaire déjà versé (une seule fois par palier)': '工资已支付（每个等级仅一次）',
            'Débloqué — salaire disponible !': '已解锁——工资可领取！',
            'En cours — encore': '进行中——还需', 'Verrouillé —': '已锁定——', 'Inviter': '邀请',
            'SALAIRE REÇU !': '工资已到账！', 'Félicitations !': '恭喜！',
            'ont été versés dans votre portefeuille.': '已存入您的钱包。',
            'Ce palier est maintenant soldé — invitez plus de filleuls actifs pour débloquer le prochain palier VIP et son salaire.': '此等级已完成——邀请更多活跃推荐人以解锁下一个VIP等级及其工资。',
            'Super, merci !': '太好了，谢谢！',
            'Cameroun': '喀麦隆', 'Rechercher un pays': '搜索国家',
            '« Ensemble, construisons la richesse de demain. »': '“让我们共同建设美好的明天。”',
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
            'Français': '法语', 'Anglais': '英语', 'Espagnol': '西班牙语', 'Chinois': '中文', 'Pakistan': '巴基斯坦',
            'Gérez vos filleuls et suivez vos gains de parrainage': '管理您的推荐人并跟踪您的推荐收益',
            'Copier': '复制', 'Nombre de personnes': '人数', 'Total commission': '佣金总额',
            'Les commissions sont calculées sur chaque dépôt validé de vos filleuls :': '佣金根据推荐人的每笔已验证充值计算：',
            'au niveau 1,': '第1级，', 'au niveau 2 et': '第2级，', 'au niveau 3.': '第3级。',
            'Un filleul doit acheter au moins une action pour effectuer un retrait.': '推荐人必须至少购买一股才能进行提现。',
            'Nv': '级别', 'Niveau': '等级', 'membre(s)': '成员',
            'Équipe niveau 1': '第1级团队', 'Équipe niveau 2': '第2级团队', 'Équipe niveau 3': '第3级团队',
            'pour le niveau suivant': '即可达到下一个等级', 'Niveau maximum atteint 🎉': '已达到最高等级 🎉',
            'Aucun filleul au niveau 1.<br>Partagez votre lien !': '暂无第1级推荐人。<br>分享您的链接！',
            'Aucun filleul au niveau 2': '暂无第2级推荐人', 'Aucun filleul au niveau 3': '暂无第3级推荐人',
            'Actif': '活跃', 'Inactif': '不活跃', 'Page': '页', 'Préc.': '上一页', 'Suiv.': '下一页',
            'Lien copié !': '链接已复制！', 'Rejoins-moi sur Groupe Dangote (GD) !': '加入我在Groupe Dangote（GD）的团队！'
        },
        ur: {
            'Accueil': 'ہوم', 'Investir': 'سرمایہ کاری', 'Salaire': 'تنخواہ', 'Équipe': 'ٹیم', 'Profil': 'پروفائل',
            'Dépôt': 'جمع', 'Retrait': 'رقم نکلوانا', 'Historique': 'تاریخ', 'Toutes vos transactions': 'آپ کے تمام لین دین',
            'Bienvenue !': 'خوش آمدید!', 'Connectez-vous à votre compte': 'اپنے اکاؤنٹ میں لاگ ان کریں',
            'Numéro de Téléphone': 'فون نمبر', 'Numéro de téléphone': 'فون نمبر',
            'Mot de passe': 'پاس ورڈ', 'Votre mot de passe': 'آپ کا پاس ورڈ',
            'Se Connecter': 'لاگ ان', 'Créer un compte': 'اکاؤنٹ بنائیں', 'ou': 'یا',
            'Rejoignez Groupe Dangote': 'ڈانگوٹے گروپ میں شامل ہوں', 'Code d’invitation': 'دعوتی کوڈ',
            'Nom complet': 'پورا نام', 'Votre nom complet': 'آپ کا پورا نام', 'Numéro WhatsApp': 'واٹس ایپ نمبر',
            'Autre': 'دیگر', 'Indicatif personnalisé': 'حسب ضرورت کوڈ', 'Aucun pays trouvé.': 'کوئی ملک نہیں ملا۔',
            'Indicatif de pays (ex. +221)': 'ملکی کوڈ (مثلاً +221)',
            'Choisissez un mot de passe': 'پاس ورڈ منتخب کریں', 'Confirmer le mot de passe': 'پاس ورڈ کی تصدیق کریں',
            'Confirmez le mot de passe': 'اپنے پاس ورڈ کی تصدیق کریں', 'Créer mon compte': 'میرا اکاؤنٹ بنائیں',
            'Déjà membre ?': 'پہلے سے رکن ہیں؟', 'Se connecter': 'لاگ ان',
            'Vous êtes invité par un ambassadeur Groupe Dangote (GD)': 'آپ کو Groupe Dangote (GD) کے سفیر نے مدعو کیا ہے',
            'Commandes': 'آرڈرز', 'Actions VIP': 'VIP شیئرز', 'Prix / action': 'فی شیئر قیمت',
            'Minimum :': 'کم از کم:', 'Jours': 'دن', 'achat(s)': 'خریداری', 'Investir': 'سرمایہ کاری کریں',
            'Montant investi :': 'سرمایہ کاری کی رقم:', 'actions': 'شیئرز', 'Historique des Transactions': 'لین دین کی تاریخ',
            'Tutoriels': 'سبق', 'Groupe Telegram': 'ٹیلیگرام گروپ', 'Panneau Admin': 'ایڈمن پینل',
            'Déconnexion': 'لاگ آؤٹ', 'Êtes-vous sûr de vouloir vous déconnecter ?': 'کیا آپ واقعی لاگ آؤٹ کرنا چاہتے ہیں؟',
            'Oui, quitter': 'ہاں، لاگ آؤٹ', 'Devise': 'کرنسی', 'Niveau max !': 'زیادہ سے زیادہ سطح!',
            'Plan actif': 'فعال منصوبہ', 'filleuls': 'ریفرلز', 'Encore': 'مزید', 'pour VIP': 'VIP کے لیے',
            'Notre industrie au service du développement': 'ہماری صنعت ترقی کی خدمت میں',
            'Nos équipes': 'ہماری ٹیمیں', 'Des femmes et des hommes engagés': 'پُرعزم خواتین اور مرد',
            'Nos installations': 'ہماری تنصیبات', 'Une industrie moderne et ambitieuse': 'جدید اور پُرعزم صنعت',
            'Notre capacité': 'ہماری صلاحیت', 'Des infrastructures conçues pour durer': 'پائیدار بنیادی ڈھانچہ',
            'L’avenir industriel': 'صنعت کا مستقبل', "L'avenir industriel": 'صنعت کا مستقبل',
            'Construire une croissance durable en Afrique': 'افریقہ میں پائیدار ترقی کی تعمیر',
            'Ensemble, construisons la richesse de demain': 'آئیں مل کر کل کی دولت بنائیں',
            'Slot': 'سلاٹ مشین', 'Actualités Communauté': 'کمیونٹی کی خبریں', 'Poster': 'پوسٹ کریں',
            'Aucun post pour le moment': 'فی الحال کوئی پوسٹ نہیں', 'Plans Actions VIP': 'VIP شیئرز کے منصوبے',
            'Taux quotidien': 'روزانہ شرح', 'Gain / jour': 'روزانہ آمدنی', 'Voir tous les plans': 'تمام منصوبے دیکھیں',
            'Bienvenue sur Groupe Dangote (GD) !': 'Groupe Dangote (GD) میں خوش آمدید!',
            'Canal Telegram': 'ٹیلیگرام چینل', 'Groupe WhatsApp': 'واٹس ایپ گروپ', 'Fermer': 'بند کریں',
            'Rejoignez notre Communauté': 'ہماری کمیونٹی میں شامل ہوں',
            'Restez connecté et ne manquez aucune opportunité d’investissement !': 'رابطے میں رہیں اور سرمایہ کاری کا کوئی موقع نہ گنوائیں!',
            'Recevez votre rémunération selon votre niveau VIP': 'اپنی VIP سطح کے مطابق آمدنی حاصل کریں',
            'NIVEAU VIP': 'VIP سطح', 'Versement unique à l’atteinte du palier': 'درجہ حاصل کرنے پر ایک بار ادائیگی',
            "Versement unique à l'atteinte du palier": 'درجہ حاصل کرنے پر ایک بار ادائیگی',
            'Débloquez votre salaire': 'اپنی تنخواہ کھولیں', 'Progression par palier VIP': 'VIP درجے کی پیش رفت',
            'Aucun palier configuré.': 'کوئی درجہ ترتیب نہیں دیا گیا۔', 'Filleuls actifs': 'فعال ریفرلز',
            'filleul(s) actif(s) requis': 'مطلوبہ فعال ریفرلز', 'filleul(s)': 'ریفرلز',
            'Salaire déjà versé (une seule fois par palier)': 'تنخواہ ادا ہوچکی ہے (ہر درجے پر ایک بار)',
            'Débloqué — salaire disponible !': 'کھلا ہوا — تنخواہ دستیاب ہے!',
            'En cours — encore': 'جاری ہے — مزید', 'Verrouillé —': 'بند ہے —', 'Inviter': 'مدعو کریں',
            'SALAIRE REÇU !': 'تنخواہ موصول ہوگئی!', 'Félicitations !': 'مبارک ہو!',
            'ont été versés dans votre portefeuille.': 'آپ کے والٹ میں جمع کر دیے گئے ہیں۔',
            'Ce palier est maintenant soldé — invitez plus de filleuls actifs pour débloquer le prochain palier VIP et son salaire.': 'یہ درجہ مکمل ہو گیا ہے — اگلا VIP درجہ اور اس کی تنخواہ کھولنے کے لیے مزید فعال ریفرلز مدعو کریں۔',
            'Super, merci !': 'بہت اچھا، شکریہ!',
            'Cameroun': 'کیمرون', 'Rechercher un pays': 'ملک تلاش کریں',
            '« Ensemble, construisons la richesse de demain. »': '“آئیں مل کر کل کی دولت بنائیں۔”',
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
            'Français': 'فرانسیسی', 'Anglais': 'انگریزی', 'Espagnol': 'ہسپانوی', 'Chinois': 'چینی', 'Pakistan': 'پاکستان',
            'Gérez vos filleuls et suivez vos gains de parrainage': 'اپنے ریفرلز کا نظم کریں اور اپنی ریفرل آمدنی دیکھیں',
            'Copier': 'کاپی کریں', 'Nombre de personnes': 'افراد کی تعداد', 'Total commission': 'کل کمیشن',
            'Les commissions sont calculées sur chaque dépôt validé de vos filleuls :': 'کمیشن آپ کے ریفرلز کی ہر تصدیق شدہ جمع رقم پر شمار کیا جاتا ہے:',
            'au niveau 1,': 'سطح 1 پر،', 'au niveau 2 et': 'سطح 2 پر اور', 'au niveau 3.': 'سطح 3 پر۔',
            'Un filleul doit acheter au moins une action pour effectuer un retrait.': 'رقم نکلوانے کے لیے ریفرل کو کم از کم ایک شیئر خریدنا ہوگا۔',
            'Nv': 'سطح', 'Niveau': 'سطح', 'membre(s)': 'رکن',
            'Équipe niveau 1': 'سطح 1 کی ٹیم', 'Équipe niveau 2': 'سطح 2 کی ٹیم', 'Équipe niveau 3': 'سطح 3 کی ٹیم',
            'pour le niveau suivant': 'اگلی سطح تک پہنچنے کے لیے', 'Niveau maximum atteint 🎉': 'زیادہ سے زیادہ سطح حاصل ہوگئی 🎉',
            'Aucun filleul au niveau 1.<br>Partagez votre lien !': 'سطح 1 پر کوئی ریفرل نہیں۔<br>اپنا لنک شیئر کریں!',
            'Aucun filleul au niveau 2': 'سطح 2 پر کوئی ریفرل نہیں', 'Aucun filleul au niveau 3': 'سطح 3 پر کوئی ریفرل نہیں',
            'Actif': 'فعال', 'Inactif': 'غیر فعال', 'Page': 'صفحہ', 'Préc.': 'پچھلا', 'Suiv.': 'اگلا',
            'Lien copié !': 'لنک کاپی ہوگیا!', 'Rejoins-moi sur Groupe Dangote (GD) !': 'Groupe Dangote (GD) میں میرے ساتھ شامل ہوں!'
        }
    };

    function getLanguage() {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        return supported.includes(stored) ? stored : 'fr';
    }

    function normalize(value) {
        return value.replace(/\s+/g, ' ').trim();
    }

    function escapeRegExp(value) {
        return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function getTranslationDictionary(language) {
        const dictionary = { ...(dictionaries[language] || {}) };
        Object.values(dictionaries).forEach((sourceDictionary) => {
            Object.entries(sourceDictionary).forEach(([french, translated]) => {
                if (dictionary[french] && !dictionary[translated]) {
                    dictionary[translated] = dictionary[french];
                }
            });
        });
        Object.entries(legacyTranslations).forEach(([source, translations]) => {
            if (translations[language]) dictionary[source] = translations[language];
        });
        Object.entries(pageTranslations).forEach(([source, translations]) => {
            if (translations[language]) dictionary[source] = translations[language];
        });
        return dictionary;
    }

    function translateValue(value, language) {
        if (language === 'fr') return value;
        const dictionary = getTranslationDictionary(language);
        const normalized = normalize(value);
        if (dictionary[normalized]) return value.replace(normalized, dictionary[normalized]);

        let translated = value;
        Object.keys(dictionary)
            .sort((a, b) => b.length - a.length)
            .forEach((source) => {
                const replacement = dictionary[source];
                if (source.includes(' ')) {
                    translated = translated.split(source).join(replacement);
                    return;
                }
                const pattern = new RegExp(
                    `(^|[^\\p{L}\\p{N}])${escapeRegExp(source)}(?=$|[^\\p{L}\\p{N}])`,
                    'gu'
                );
                translated = translated.replace(pattern, (match, prefix) => `${prefix}${replacement}`);
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

        const walker = document.createTreeWalker(
            document.body,
            (window.NodeFilter && window.NodeFilter.SHOW_TEXT) || 4
        );
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach((node) => {
            if (!shouldSkip(node)) {
                if (!originalText.has(node)) originalText.set(node, node.nodeValue);
                const translated = translateValue(originalText.get(node), language);
                if (node.nodeValue !== translated) node.nodeValue = translated;
            }
        });

        document.querySelectorAll('[placeholder], [title], [aria-label], [data-name]').forEach((element) => {
            ['placeholder', 'title', 'aria-label', 'data-name'].forEach((attribute) => {
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
        document.documentElement.classList.remove('gd-language-pending');
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

    window.GDLanguage = {
        getLanguage,
        setLanguage,
        translateDom,
        translate: (value) => translateValue(String(value || ''), getLanguage())
    };
    document.addEventListener('DOMContentLoaded', () => {
        initializePicker();
        translateDom();
    });
})();