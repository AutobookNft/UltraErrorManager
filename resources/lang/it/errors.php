<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Error Messages - Italian
    |--------------------------------------------------------------------------
    */

    'dev' => [
        // == Existing Entries ==
        'authentication_error' => 'Tentativo di accesso non autenticato.',
        'scan_error' => 'Si è verificato un errore durante la scansione antivirus per il file: :filename.',
        'virus_found' => 'È stato rilevato un virus nel file: :filename.',
        'invalid_file_extension' => 'Il file ha un\'estensione non valida (:extension).',
        'max_file_size' => 'Il file (:size) supera la dimensione massima consentita (:max_size).',
        'invalid_file_pdf' => 'Il file PDF fornito non è valido o corrotto.',
        'mime_type_not_allowed' => 'Il tipo MIME del file (:mime) non è consentito.',
        'invalid_image_structure' => 'La struttura del file immagine non è valida.',
        'invalid_file_name' => 'Nome file non valido ricevuto durante il processo di upload: :filename.',
        'error_getting_presigned_url' => 'Si è verificato un errore durante il recupero dell\'URL presigned per :object.',
        'error_during_file_upload' => 'Si è verificato un errore durante il processo di caricamento del file per :filename.',
        'unable_to_save_bot_file' => 'Impossibile salvare il file per il bot: :filename.',
        'unable_to_create_directory' => 'Impossibile creare la directory per il caricamento del file: :directory.',
        'unable_to_change_permissions' => 'Impossibile modificare i permessi per il file/directory: :path.',
        'impossible_save_file' => 'È stato impossibile salvare il file: :filename sul disco :disk.',
        'error_during_create_egi_record' => 'Si è verificato un errore durante la creazione del record EGI nel database.',
        'error_during_file_name_encryption' => 'Si è verificato un errore durante il processo di crittografia del nome del file.',
        'imagick_not_available' => 'L\'estensione PHP Imagick non è disponibile o configurata correttamente.',
        'json_error' => 'Errore di elaborazione JSON. Tipo: :type, Messaggio: :message',
        'generic_server_error' => 'Si è verificato un errore generico del server. Dettagli: :details',
        'file_not_found' => 'Il file richiesto non è stato trovato: :path.',
        'unexpected_error' => 'Errore imprevisto nel sistema. Controllare i log per dettagli.',
        'error_deleting_local_temp_file' => 'Impossibile eliminare il file temporaneo locale: :path.',
        'acl_setting_error' => 'Si è verificato un errore durante l\'impostazione dell\'ACL (:acl) per l\'oggetto :object.',
        'invalid_input' => 'Input non valido fornito per il parametro :param.',
        'temp_file_not_found' => 'File temporaneo non trovato al percorso: :path.',
        'error_deleting_ext_temp_file' => 'Impossibile eliminare il file temporaneo esterno: :path.',
        'ucm_delete_failed' => 'Impossibile eliminare la configurazione con chiave :key: :message',
        'undefined_error_code' => 'Codice di errore non definito incontrato: :errorCode. Codice originale era [:_original_code].',
        'fallback_error' => 'Si è verificato un errore ma non è stata trovata alcuna configurazione specifica per il codice [:_original_code].',
        'fatal_fallback_failure' => 'ERRORE FATALE: Configurazione di fallback mancante o non valida. Il sistema non può rispondere.',
        'ucm_audit_not_found' => 'Nessun record di audit trovato per l\'ID di configurazione specificato: :id.',
        'ucm_duplicate_key' => 'Tentativo di creare una configurazione con una chiave duplicata: :key.',
        'ucm_create_failed' => 'Creazione voce di configurazione fallita: :key. Motivo: :reason',
        'ucm_update_failed' => 'Aggiornamento voce di configurazione fallita: :key. Motivo: :reason',
        'ucm_not_found' => 'Chiave di configurazione non trovata: :key.',
        'invalid_file' => 'File fornito non valido: :reason',
        'invalid_file_validation' => 'Validazione file fallita per il campo :field. Motivo: :reason',
        'error_saving_file_metadata' => 'Salvataggio metadati fallito per file ID :file_id. Motivo: :reason',
        'server_limits_restrictive' => 'I limiti del server potrebbero essere troppo restrittivi. Controllare :limit_name (:limit_value).',
        'egi_auth_required' => 'Autenticazione richiesta per l\'upload dell\'EGI.',
        'egi_file_input_error' => "Input 'file' mancante o non valido. Codice errore upload: :code",
        'egi_validation_failed' => 'Validazione metadati EGI fallita. Controllare errori di validazione nel contesto.',
        'egi_collection_init_error' => 'Errore critico inizializzando la collection di default per l\'utente :user_id.',
        'egi_crypto_error' => 'Fallita la cifratura del nome file: :filename',
        'egi_db_error' => 'Errore database processando l\'EGI :egi_id per la collection :collection_id.',
        'egi_storage_critical_failure' => 'Fallimento critico nel salvataggio del file EGI :egi_id sul/i disco/hi: :disks',
        'egi_storage_config_error' => "Il disco di storage 'local' richiesto per il fallback non è configurato.",
        'egi_unexpected_error' => 'Errore inaspettato durante l\'elaborazione dell\'EGI per il file :original_filename.',

        // Errori relativi all'interfaccia utente (messaggi per sviluppatori)
        'egi_unauthorized_access' => 'Tentativo di accesso non autenticato alla pagina di upload EGI.',
        'egi_page_access_notice' => 'Pagina di upload EGI acceduta con successo dall\'amministratore con ID :user_id.',
        'egi_page_rendering_error' => 'Eccezione durante il rendering della pagina di upload EGI: :exception_message',

        // Errori di validazione (messaggi per sviluppatori)
        'invalid_egi_file' => 'Validazione del file EGI fallita con errori: :validation_errors',

        // Errori di elaborazione (messaggi per sviluppatori)
        'error_during_egi_processing' => 'Errore durante l\'elaborazione del file EGI nella fase ":processing_stage": :exception_message',

        // == New Entries ==
        'authorization_error' => 'Autorizzazione negata per l\'azione richiesta: :action.',
        'csrf_token_mismatch' => 'Token CSRF non valido o scaduto.',
        'route_not_found' => 'Il percorso o la risorsa richiesta non è stata trovata: :url.',
        'method_not_allowed' => 'Metodo HTTP :method non consentito per questo percorso: :url.',
        'too_many_requests' => 'Troppe richieste rilevate dal limitatore di frequenza.',
        'database_error' => 'Si è verificato un errore di query o connessione al database. Dettagli: :details',
        'record_not_found' => 'Il record richiesto dal database non è stato trovato (Modello: :model, ID: :id).',
        'validation_error' => 'Validazione input fallita. Controllare il contesto per errori specifici.', // Messaggio dev generico
        'utm_load_failed' => 'Caricamento file traduzioni fallito: :file per la lingua :locale.',
        'utm_invalid_locale' => 'Tentativo di usare una lingua non valida o non supportata: :locale.',
        'uem_email_send_failed' => 'EmailNotificationHandler: invio notifica fallito per :errorCode. Motivo: :reason',
        'uem_slack_send_failed' => 'SlackNotificationHandler: invio notifica fallito per :errorCode. Motivo: :reason',
        'uem_recovery_action_failed' => 'Azione di recupero :action fallita per errore :errorCode. Motivo: :reason',
        
        'role_user_not_found' => 'Utente con ID :user_id non trovato durante l\'operazione sul ruolo ":role"',
        'role_assignment_failed' => 'Impossibile assegnare il ruolo ":role" all\'utente :user_id: :error_message',
        'role_check_failed' => 'Errore durante la verifica se l\'utente :user_id ha il ruolo ":role": :error_message',
        'role_users_retrieval_failed' => 'Errore nel recupero degli utenti con ruolo ":role": :error_message',
        'role_not_found' => 'Ruolo ":role" non trovato durante l\'operazione sui permessi utente',

        'wallet_creation_failed' => 'Impossibile creare il wallet per la collezione :collection_id, utente :user_id: :error_message',
        'wallet_quota_check_error' => 'Errore durante il controllo della quota del wallet per l\'utente :user_id, collezione :collection_id: :error_message',
        'wallet_insufficient_quota' => 'L\'utente :user_id ha quota insufficiente per la collezione :collection_id. Richiesto: mint=:required_mint_quota, rebind=:required_rebind_quota. Disponibile: mint=:current_mint_quota, rebind=:current_rebind_quota.',
        'wallet_address_invalid' => 'Formato dell\'indirizzo wallet non valido per l\'utente :user_id: :wallet_address',
        'wallet_not_found' => 'Wallet non trovato per l\'utente :user_id e la collezione :collection_id',
        'wallet_already_exists' => 'Il wallet esiste già per l\'utente :user_id e la collezione :collection_id con ID :wallet_id',
    ],

    'user' => [
        // == Existing Entries ==
        'authentication_error' => 'Non hai l\'autorizzazione per eseguire questa operazione.',
        'scan_error' => 'Non è stato possibile verificare la sicurezza del file in questo momento. Riprova più tardi.',
        'virus_found' => 'Il file ":fileName" contiene potenziali minacce ed è stato bloccato per la tua sicurezza.',
        'invalid_file_extension' => 'L\'estensione del file non è supportata. Le estensioni consentite sono: :allowed_extensions.',
        'max_file_size' => 'Il file è troppo grande. La dimensione massima consentita è :max_size.',
        'invalid_file_pdf' => 'Il PDF caricato non è valido o potrebbe essere danneggiato. Riprova.',
        'mime_type_not_allowed' => 'Il tipo di file che hai caricato non è supportato. I tipi consentiti sono: :allowed_types.',
        'invalid_image_structure' => 'L\'immagine che hai caricato non sembra valida. Prova con un\'altra immagine.',
        'invalid_file_name' => 'Il nome del file contiene caratteri non validi. Usa solo lettere, numeri, spazi, trattini e underscore.',
        'error_getting_presigned_url' => 'Si è verificato un problema temporaneo durante la preparazione del caricamento. Riprova.',
        'error_during_file_upload' => 'Si è verificato un errore durante il caricamento del file. Riprova o contatta l\'assistenza se il problema persiste.',
        'unable_to_save_bot_file' => 'Non è stato possibile salvare il file generato in questo momento. Riprova più tardi.',
        'unable_to_create_directory' => 'Errore interno del sistema durante la preparazione del salvataggio. Contatta l\'assistenza.',
        'unable_to_change_permissions' => 'Errore interno del sistema durante il salvataggio del file. Contatta l\'assistenza.',
        'impossible_save_file' => 'Non è stato possibile salvare il tuo file a causa di un errore di sistema. Riprova o contatta l\'assistenza.',
        'error_during_create_egi_record' => 'Si è verificato un errore durante il salvataggio delle informazioni. Il nostro team tecnico è stato informato.',
        'error_during_file_name_encryption' => 'Si è verificato un errore di sicurezza durante l\'elaborazione del file. Riprova.',
        'imagick_not_available' => 'Il sistema non è momentaneamente in grado di elaborare le immagini. Contatta l\'amministratore se il problema persiste.',
        'json_error' => 'Si è verificato un errore nell\'elaborazione dei dati. Controlla i dati inseriti o riprova più tardi. [Rif: JSON]',
        'generic_server_error' => 'Si è verificato un errore del server. Riprova più tardi o contatta l\'assistenza se il problema continua. [Rif: SERVER]',
        'file_not_found' => 'Il file richiesto non è stato trovato.',
        'unexpected_error' => 'Si è verificato un errore imprevisto. Il nostro team tecnico è stato informato. Riprova più tardi. [Rif: UNEXPECTED]',
        'error_deleting_local_temp_file' => 'Errore interno durante la pulizia dei file temporanei. Contatta l\'assistenza.', // Messaggio più generico per l'utente
        'acl_setting_error' => 'Non è stato possibile impostare i permessi corretti per il file. Riprova o contatta l\'assistenza.',
        'invalid_input' => 'Il valore fornito per :param non è valido. Controlla l\'input e riprova.',
        'temp_file_not_found' => 'Si è verificato un problema temporaneo con il file :file. Riprova.',
        'error_deleting_ext_temp_file' => 'Errore interno durante la pulizia dei file temporanei esterni. Contatta l\'assistenza.',
        'ucm_delete_failed' => 'Si è verificato un errore durante l\'eliminazione della configurazione. Riprova più tardi.',
        'undefined_error_code' => 'Si è verificato un errore imprevisto. Contatta il supporto se il problema persiste. [Rif: UNDEFINED]',
        'fallback_error' => 'Si è verificato un problema inatteso nel sistema. Riprova più tardi o contatta l\'assistenza. [Rif: FALLBACK]',
        'fatal_fallback_failure' => 'Si è verificato un errore critico nel sistema. Contatta immediatamente l\'assistenza. [Rif: FATAL]',
        'ucm_audit_not_found' => 'Non sono disponibili informazioni storiche per questo elemento.',
        'ucm_duplicate_key' => 'Questa impostazione di configurazione esiste già.',
        'ucm_create_failed' => 'Impossibile salvare la nuova impostazione di configurazione. Riprova.',
        'ucm_update_failed' => 'Impossibile aggiornare l\'impostazione di configurazione. Riprova.',
        'ucm_not_found' => 'L\'impostazione di configurazione richiesta non è stata trovata.',
        'invalid_file' => 'Il file fornito non è valido. Controlla il file e riprova.',
        'invalid_file_validation' => 'Controlla il file nel campo :field. La validazione non è riuscita.',
        'error_saving_file_metadata' => 'Si è verificato un errore salvando i dettagli del file. Riprova il caricamento.',
        'server_limits_restrictive' => 'La configurazione del server potrebbe impedire questa operazione. Contatta l\'assistenza se il problema persiste.',
        'generic_internal_error' => 'Si è verificato un errore interno. Il nostro team tecnico è stato informato e sta lavorando per risolverlo.', // Messaggio generico riutilizzabile
        'egi_auth_required' => 'Effettua il login per caricare un EGI.',
        'egi_file_input_error' => 'Seleziona un file valido da caricare.',
        'egi_validation_failed' => 'Correggi i campi evidenziati nel modulo.',
        'egi_collection_init_error' => 'Impossibile preparare la tua collection. Contatta il supporto se il problema persiste.',
        'egi_storage_failure' => 'Fallito il salvataggio sicuro del file EGI. Riprova o contatta il supporto.',
        'egi_unexpected_error' => 'Si è verificato un errore inaspettato durante l\'elaborazione del tuo EGI. Riprova più tardi.',

         // Errori relativi all'interfaccia utente (messaggi per utenti)
         'egi_unauthorized_access' => 'È necessaria l\'autenticazione per accedere a questa pagina. Effettua il login.',
         'egi_page_rendering_error' => 'Si è verificato un problema durante il caricamento della pagina. Riprova più tardi o contatta l\'assistenza.',
 
         // Errori di validazione (messaggi per utenti)
         'invalid_egi_file' => 'Il file EGI non può essere elaborato a causa di errori di validazione. Verifica il formato e il contenuto del file.',
          
         // Errori di elaborazione (messaggi per utenti)
         'error_during_egi_processing' => 'Si è verificato un errore durante l\'elaborazione del file EGI. Il nostro team è stato avvisato e analizzerà il problema.',

        // == New Entries ==
        'authorization_error' => 'Non disponi dei permessi necessari per eseguire questa azione.',
        'csrf_token_mismatch' => 'La tua sessione è scaduta o non è valida. Per favore, ricarica la pagina e riprova.',
        'route_not_found' => 'La pagina o la risorsa che hai richiesto non è stata trovata.',
        'method_not_allowed' => 'L\'azione che hai tentato di eseguire non è permessa su questa risorsa.',
        'too_many_requests' => 'Stai eseguendo azioni troppo rapidamente. Attendi qualche istante e riprova.',
        'database_error' => 'Si è verificato un errore nel database. Riprova più tardi o contatta l\'assistenza. [Rif: DB]',
        'record_not_found' => 'L\'elemento che hai richiesto non è stato trovato.',
        'validation_error' => 'Per favore, correggi gli errori evidenziati nel modulo e riprova.', // Messaggio user generico
        'utm_load_failed' => 'Il sistema ha riscontrato un problema nel caricamento delle impostazioni della lingua. Alcune funzionalità potrebbero essere limitate.',
        'utm_invalid_locale' => 'L\'impostazione della lingua richiesta non è disponibile.',
        // Messaggi user per errori interni UEM (usare generic_internal_error)
        'uem_email_send_failed' => null,
        'uem_slack_send_failed' => null,
        'uem_recovery_action_failed' => null,
        
        'role_user_not_found' => 'L\'utente specificato non è stato trovato.',
        'role_assignment_failed' => 'Si è verificato un problema durante l\'aggiornamento dei permessi utente. Riprova o contatta l\'assistenza.',
        'role_users_retrieval_failed' => 'Impossibile caricare l\'elenco degli utenti. Riprova più tardi.',
        'role_not_found' => 'Il livello di permesso richiesto non è disponibile nel sistema.',

        'wallet_creation_failed' => 'Si è verificato un problema durante la configurazione del wallet per questa collezione. Il nostro team è stato avvisato e risolverà questo problema.',
        'wallet_insufficient_quota' => 'Non hai quota royalty sufficiente per questa operazione. Modifica i valori di royalty e riprova.',
        'wallet_address_invalid' => 'L\'indirizzo del wallet fornito non è valido. Controlla il formato e riprova.',
        'wallet_not_found' => 'Il wallet richiesto non è stato trovato. Verifica le tue informazioni e riprova.',
        'wallet_already_exists' => 'Un wallet è già configurato per questa collezione. Utilizza il wallet esistente o contatta l\'assistenza per aiuto.',
    ],

    'generic_error' => 'Si è verificato un errore. Riprova più tardi o contatta l\'assistenza.',
];
