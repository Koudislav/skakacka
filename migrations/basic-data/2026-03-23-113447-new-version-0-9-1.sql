INSERT INTO `system_updates` 
(`title`, `message`, `message_mail`, `version`, `type`, `created_at`)
VALUES
(
	'verze 0.9.1 - struktura článků s vnořováním',
	'<ul>
		<li>Podpora vnořování článků (hierarchie)</li>
		<li>Breadcrumbs navigace a její nastavení v editoru</li>
	</ul>',
	'<p style="color:#333;font-size:14px;margin:20px 0;">
		V této verzi přidáváme podporu vnořování článků, která umožňuje vytvářet hierarchickou strukturu obsahu. Nově tak můžete organizovat stránky do více úrovní místo původního plochého uspořádání.
	</p>
	<p style="color:#333;font-size:14px;margin:20px 0;">
		Novinkou je také breadcrumbs (drobečková) navigace, která zlepší orientaci návštěvníků na vašem webu. V editoru je k ní široká konfigurace, takže můžete snadno definovat, jak se bude tato navigace zobrazovat.
	</p>
	<p style="color:#333;font-size:14px;margin:20px 0;">
		Tato aktualizace přináší významné vylepšení pro správu obsahu a uživatelskou zkušenost.
		<br>
		Vnořování článků umožňuje lepší organizaci a strukturování informací, zatímco breadcrumbs navigace usnadňuje návštěvníkům orientaci a zlepšuje celkovou použitelnost vašeho webu.
	</p>
	<p style="color:#333;font-size:14px;margin:20px 0;">
		Zatímco dříve byla struktura všech stránek na webu na první úrovni, například "Domů / Služba", nyní můžete mít více úrovní, například "Domů / Služby / Kategorie / Služba". To umožňuje lepší organizaci obsahu a zlepšuje SEO vašeho webu.
	</p>
	<p style="color:#333;font-size:14px;margin:20px 0;">
		Tato změna zároveň tvoří základ pro další rozšíření práce se strukturou obsahu v budoucích verzích.
	</p>
	<p><strong>Co je nového:</strong></p>
	<ul style="padding-left:18px;color:#333;font-size:14px;line-height:1.6;">
		<li>Podpora vnořování článků (hierarchie)</li>
		<li>Breadcrumbs navigace a její nastavení v editoru</li>
	</ul>',
	'0.9.1',
	'info',
	'2026-03-23 15:30:00'
);