@extends('layouts.marketing')

@section('title', 'Privacy Policy')
@section('description', 'Informativa sul trattamento dei dati personali ai sensi del Regolamento UE 2016/679 (GDPR).')

@section('content')
<article class="max-w-3xl mx-auto px-6 py-20">

    <header class="mb-12">
        <p class="text-xs font-semibold text-teal-600 uppercase tracking-widest mb-3">Informativa privacy</p>
        <h1 class="text-4xl font-bold text-gray-900 mb-4">Privacy Policy</h1>
        <p class="text-gray-500">Ultimo aggiornamento: {{ \Carbon\Carbon::now()->format('d/m/Y') }}</p>
    </header>

    <div class="prose prose-gray prose-lg max-w-none">

        <h2>1. Titolare del trattamento</h2>
        <p>
            Il titolare del trattamento dei dati personali è <strong>GestionalePro</strong> (di seguito "la Società"),
            raggiungibile all'indirizzo email <a href="{{ route('contact') }}">modulo di contatto</a>.
        </p>
        <p>
            Per qualsiasi richiesta relativa al trattamento dei tuoi dati personali puoi scrivere a
            <a href="mailto:privacy@example.com">privacy@example.com</a>.
        </p>

        <h2>2. Dati raccolti e finalità del trattamento</h2>

        <h3>2.1 Dati forniti direttamente dall'utente</h3>
        <p>Raccogliamo i seguenti dati quando ti registri o utilizzi il servizio:</p>
        <ul>
            <li><strong>Dati identificativi:</strong> nome, cognome, indirizzo email, numero di telefono.</li>
            <li><strong>Dati di prenotazione:</strong> servizi richiesti, data e ora degli appuntamenti, note.</li>
            <li><strong>Dati di pagamento:</strong> i dati di carta/conto vengono trattati esclusivamente da Stripe, Inc. (PCI-DSS Level 1). GestionalePro non memorizza numeri di carta.</li>
        </ul>

        <h3>2.2 Dati raccolti automaticamente</h3>
        <ul>
            <li><strong>Dati di navigazione:</strong> indirizzo IP, tipo di browser, pagine visitate, ora di accesso.</li>
            <li><strong>Cookie tecnici:</strong> necessari al funzionamento del servizio (sessione, autenticazione). Non utilizziamo cookie di profilazione o tracciamento di terze parti senza il tuo consenso.</li>
        </ul>

        <h3>2.3 Finalità e basi giuridiche</h3>
        <table>
            <thead>
                <tr><th>Finalità</th><th>Base giuridica (GDPR)</th></tr>
            </thead>
            <tbody>
                <tr><td>Erogazione del servizio di gestione prenotazioni</td><td>Esecuzione del contratto (art. 6.1.b)</td></tr>
                <tr><td>Invio promemoria e notifiche di appuntamento</td><td>Esecuzione del contratto (art. 6.1.b)</td></tr>
                <tr><td>Gestione dei pagamenti tramite Stripe</td><td>Esecuzione del contratto (art. 6.1.b)</td></tr>
                <tr><td>Adempimenti fiscali e contabili</td><td>Obbligo legale (art. 6.1.c)</td></tr>
                <tr><td>Comunicazioni di marketing (newsletter)</td><td>Consenso (art. 6.1.a)</td></tr>
                <tr><td>Prevenzione frodi e sicurezza</td><td>Legittimo interesse (art. 6.1.f)</td></tr>
            </tbody>
        </table>

        <h2>3. Conservazione dei dati</h2>
        <p>
            I dati relativi agli account attivi vengono conservati per l'intera durata del rapporto contrattuale.
            Dopo la cancellazione dell'account, i dati vengono eliminati entro 30 giorni, salvo obblighi di
            conservazione fiscale (10 anni per dati contabili ai sensi del D.P.R. 600/1973).
        </p>

        <h2>4. Destinatari dei dati</h2>
        <p>I dati possono essere comunicati a:</p>
        <ul>
            <li><strong>Stripe, Inc.</strong> – elaborazione dei pagamenti (privacy policy: stripe.com/it/privacy)</li>
            <li><strong>Provider di hosting</strong> – server cloud per l'erogazione del servizio, in UE o con garanzie equivalenti (SCC)</li>
            <li><strong>Provider di notifiche</strong> – invio di email e SMS (Mailgun, Twilio o equivalenti), in qualità di responsabili del trattamento</li>
            <li><strong>Autorità pubbliche</strong> – quando richiesto dalla legge</li>
        </ul>
        <p>I dati non vengono mai venduti a terzi.</p>

        <h2>5. Trasferimenti internazionali</h2>
        <p>
            Alcuni fornitori (es. Stripe) sono stabiliti negli USA. Il trasferimento avviene nel rispetto del
            GDPR tramite le Clausole Contrattuali Standard (SCC) approvate dalla Commissione Europea
            o in presenza di adeguate garanzie.
        </p>

        <h2>6. I tuoi diritti</h2>
        <p>Ai sensi degli artt. 15-22 del GDPR hai il diritto di:</p>
        <ul>
            <li><strong>Accesso</strong> – ottenere conferma che stiamo trattando i tuoi dati e riceverne copia.</li>
            <li><strong>Rettifica</strong> – correggere dati inesatti o incompleti.</li>
            <li><strong>Cancellazione</strong> – chiedere la cancellazione dei tuoi dati ("diritto all'oblio").</li>
            <li><strong>Limitazione</strong> – limitare il trattamento in determinati casi.</li>
            <li><strong>Portabilità</strong> – ricevere i tuoi dati in formato strutturato e leggibile da macchina.</li>
            <li><strong>Opposizione</strong> – opporti al trattamento basato sul legittimo interesse.</li>
            <li><strong>Revoca del consenso</strong> – revocare il consenso in qualsiasi momento senza pregiudicare la liceità del trattamento precedente.</li>
        </ul>
        <p>
            Per esercitare i tuoi diritti scrivi a <a href="mailto:privacy@example.com">privacy@example.com</a>.
            Risponderemo entro 30 giorni. Hai inoltre il diritto di proporre reclamo al Garante per la
            Protezione dei Dati Personali (<a href="https://www.garanteprivacy.it" target="_blank" rel="noopener">garanteprivacy.it</a>).
        </p>

        <h2>7. Cookie</h2>
        <p>
            Utilizziamo esclusivamente cookie tecnici necessari al funzionamento del servizio
            (cookie di sessione, token CSRF, preferenze UI). Nessun cookie di tracciamento o profilazione
            viene impostato senza il tuo consenso esplicito.
        </p>

        <h2>8. Sicurezza</h2>
        <p>
            I dati sono protetti da misure tecniche e organizzative adeguate: connessioni cifrate TLS,
            hashing delle password, accesso limitato ai soli incaricati autorizzati e backup regolari.
        </p>

        <h2>9. Modifiche alla presente informativa</h2>
        <p>
            Ci riserviamo di aggiornare questa informativa. Le modifiche sostanziali saranno comunicate
            via email o tramite avviso in evidenza nel servizio. La versione in vigore è sempre disponibile
            a questo indirizzo.
        </p>

    </div>

    <div class="mt-16 pt-8 border-t border-gray-100 text-sm text-gray-400">
        <p>Hai domande? Scrivici a <a href="mailto:privacy@example.com" class="text-teal-600 hover:underline">privacy@example.com</a></p>
    </div>

</article>
@endsection
