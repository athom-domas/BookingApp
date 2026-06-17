@extends('layouts.marketing')

@section('title', 'Termini di servizio')
@section('description', 'Termini e condizioni di utilizzo del software GestionalePro.')

@section('content')
<article class="max-w-3xl mx-auto px-6 py-20">

    <header class="mb-12">
        <p class="text-xs font-semibold text-terra uppercase tracking-widest mb-3">Termini e condizioni</p>
        <h1 class="font-display text-4xl font-semibold text-ink mb-4">Termini di servizio</h1>
        <p class="text-ink-muted">Ultimo aggiornamento: {{ \Carbon\Carbon::now()->format('d/m/Y') }}</p>
    </header>

    <div class="prose prose-stone prose-lg max-w-none">

        <h2>1. Accettazione dei termini</h2>
        <p>
            Accedendo o utilizzando GestionalePro (il "Servizio") accetti integralmente i presenti Termini di Servizio.
            Se utilizzi il Servizio per conto di un'azienda, dichiari di avere l'autorità per vincolare tale azienda.
            Se non accetti questi termini, non puoi utilizzare il Servizio.
        </p>

        <h2>2. Descrizione del servizio</h2>
        <p>
            GestionalePro è un software SaaS (Software as a Service) per la gestione di prenotazioni,
            staff e pagamenti destinato a saloni, barberie e centri estetici. Il Servizio include:
        </p>
        <ul>
            <li>Portale di prenotazione online per i clienti finali</li>
            <li>Pannello di amministrazione per gestire appuntamenti, staff e reportistica</li>
            <li>Integrazione con Stripe per i pagamenti online</li>
            <li>Sistema di notifiche via email e WhatsApp</li>
            <li>Lista d'attesa automatizzata</li>
        </ul>

        <h2>3. Account e accesso</h2>
        <p>
            Per utilizzare il Servizio devi creare un account fornendo informazioni accurate e complete.
            Sei responsabile della riservatezza delle credenziali di accesso e di tutte le attività
            compiute tramite il tuo account. In caso di accesso non autorizzato, notificacelo
            immediatamente tramite il <a href="{{ route('contact') }}">modulo di contatto</a>.
        </p>
        <p>
            Non puoi cedere, trasferire o condividere il tuo account con terzi senza il nostro consenso scritto.
        </p>

        <h2>4. Abbonamento e pagamenti</h2>

        <h3>4.1 Prova gratuita</h3>
        <p>
            Offriamo una prova gratuita di 14 giorni senza necessità di carta di credito.
            Al termine del periodo di prova, per continuare a utilizzare il Servizio è necessario
            attivare un abbonamento a pagamento.
        </p>

        <h3>4.2 Piani e prezzi</h3>
        <p>
            I prezzi vigenti sono quelli pubblicati nella pagina <a href="{{ url('/') }}#prezzi">Prezzi</a> del sito.
            Tutti i prezzi si intendono IVA esclusa. L'IVA applicabile è quella prevista dalla normativa italiana.
        </p>

        <h3>4.3 Fatturazione</h3>
        <p>
            Il canone viene addebitato mensilmente in anticipo. I pagamenti non rimborsati o respinti
            possono comportare la sospensione dell'account. Emitteremo fattura elettronica ai sensi
            della normativa italiana vigente.
        </p>

        <h3>4.4 Modifiche di prezzo</h3>
        <p>
            Ci riserviamo di modificare i prezzi con un preavviso di almeno 30 giorni via email.
            Continuando a utilizzare il Servizio dopo la modifica del prezzo, accetti il nuovo importo.
        </p>

        <h2>5. Cancellazione e recesso</h2>
        <p>
            Puoi cancellare il tuo abbonamento in qualsiasi momento dalla dashboard o contattando
            il supporto. La cancellazione ha effetto alla fine del periodo di fatturazione corrente:
            non sono previsti rimborsi per i periodi già pagati, salvo diverso accordo scritto.
        </p>
        <p>
            Ci riserviamo di sospendere o cancellare l'account in caso di violazione dei presenti
            Termini, con preavviso adeguato salvo violazioni gravi.
        </p>

        <h2>6. Proprietà intellettuale</h2>
        <p>
            Il Servizio, il software sottostante, il design e i marchi sono di esclusiva proprietà
            della Società. Ti concediamo una licenza limitata, non esclusiva e non trasferibile
            per utilizzare il Servizio secondo questi Termini.
        </p>
        <p>
            I dati che inserisci nel Servizio (clienti, appuntamenti, ecc.) rimangono di tua proprietà.
            Puoi esportarli in qualsiasi momento in formato CSV o tramite le funzioni di export disponibili.
        </p>

        <h2>7. Dati dei clienti e GDPR</h2>
        <p>
            Utilizzando il Servizio per raccogliere e gestire dati personali dei tuoi clienti,
            agisci come Titolare del Trattamento ai sensi del GDPR. GestionalePro opera come
            Responsabile del Trattamento per tuo conto. I dettagli sono disciplinati dal Data
            Processing Agreement (DPA) disponibile su richiesta.
        </p>
        <p>
            Sei responsabile di informare i tuoi clienti del trattamento dei loro dati personali
            e di ottenere i consensi eventualmente necessari.
        </p>

        <h2>8. Limitazione di responsabilità</h2>
        <p>
            Il Servizio è fornito "così com'è". Pur facendo il possibile per garantire continuità
            e affidabilità, non offriamo garanzie di disponibilità ininterrotta. Non saremo
            responsabili per danni indiretti, consequenziali o perdita di profitto derivanti
            dall'uso o dall'impossibilità di usare il Servizio.
        </p>
        <p>
            La nostra responsabilità complessiva nei tuoi confronti non supera l'importo pagato
            nei 3 mesi precedenti all'evento che ha causato il danno.
        </p>

        <h2>9. Uso accettabile</h2>
        <p>Non puoi utilizzare il Servizio per:</p>
        <ul>
            <li>Attività illegali o fraudolente</li>
            <li>Trasmettere malware, virus o codice dannoso</li>
            <li>Tentare di accedere a sistemi non autorizzati</li>
            <li>Inviare comunicazioni commerciali non richieste (spam)</li>
            <li>Raccogliere dati personali in violazione del GDPR o normative applicabili</li>
        </ul>

        <h2>10. Legge applicabile e foro competente</h2>
        <p>
            I presenti Termini sono regolati dalla legge italiana. Per qualsiasi controversia
            derivante da o connessa a questi Termini, sarà competente in via esclusiva il
            Tribunale di Milano, salvo diversa previsione inderogabile di legge.
        </p>
        <p>
            Per i consumatori (persone fisiche che non agiscono nell'esercizio di attività
            professionale) si applica la normativa a tutela del consumatore prevista dal
            D.Lgs. 206/2005 (Codice del Consumo).
        </p>

        <h2>11. Modifiche ai termini</h2>
        <p>
            Possiamo aggiornare questi Termini. Le modifiche sostanziali saranno comunicate
            con almeno 15 giorni di preavviso via email. Continuando a utilizzare il Servizio
            dopo la comunicazione, accetti i nuovi termini.
        </p>

        <h2>12. Contatti</h2>
        <p>
            Per domande su questi Termini scrivi a <a href="mailto:info@booking-app.it">info@booking-app.it</a>
            oppure tramite il <a href="{{ route('contact') }}">modulo di contatto</a>.
        </p>

    </div>

    <div class="mt-16 pt-8 border-t border-warm-border text-sm text-ink-muted/70">
        <p>Hai domande? Scrivici a <a href="{{ route('contact') }}" class="text-terra hover:underline">modulo di contatto</a></p>
    </div>

</article>
@endsection
