<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Error Messages - Italian
    |--------------------------------------------------------------------------
    |
    | The following language lines are used for error messages displayed to the user
    | and to developers. You are free to modify these language lines according to your
    | application's requirements.
    |
    */

    'dev' => [
        // Authentication and Authorization
        'authentication_error' => 'Tentativo di accesso non autenticato.',

        'ucm_delete_failed' => 'Impossibile eliminare la configurazione con chiave :key: :message',
        'undefined_error_code' => 'Codice di errore non definito: :errorCode',

        // File Validation
        'invalid_input' => 'Input non valido fornito per il parametro :param.',
        'invalid_image_structure' => 'La struttura del file immagine non è valida.',
        'mime_type_not_allowed' => 'Il tipo MIME del file non è consentito.',
        'max_file_size' => 'Il file supera la dimensione massima consentita.',
        'invalid_file_extension' => 'Il file ha un\'estensione non valida.',
        'invalid_file_name' => 'Nome file non valido ricevuto durante il processo di upload.',
        'invalid_file_pdf' => 'Il file PDF non è valido.',

        // Virus and Security
        'virus_found' => 'È stato rilevato un virus nel file.',
        'scan_error' => 'Si è verificato un errore durante la scansione antivirus.',

        // File Storage and IO
        'temp_file_not_found' => 'File temporaneo non trovato.',
        'file_not_found' => 'Il file richiesto non è stato trovato.',
        'error_getting_presigned_url' => 'Si è verificato un errore durante il recupero dell\'URL presigned.',
        'error_during_file_upload' => 'Si è verificato un errore durante il processo di caricamento del file.',
        'error_deleting_local_temp_file' => 'Impossibile eliminare il file temporaneo locale.',
        'error_deleting_ext_temp_file' => 'Impossibile eliminare il file temporaneo esterno.',
        'unable_to_save_bot_file' => 'Impossibile salvare il file per il bot.',
        'unable_to_create_directory' => 'Impossibile creare la directory per il caricamento del file.',
        'unable_to_change_permissions' => 'Impossibile modificare i permessi del file.',
        'impossible_save_file' => 'È stato impossibile salvare il file.',

        // Database and Record Management
        'error_during_create_egi_record' => 'Si è verificato un errore durante la creazione del record EGI nel database.',

        // Security and Encryption
        'error_during_file_name_encryption' => 'Si è verificato un errore durante il processo di crittografia del nome del file.',
        'acl_setting_error' => 'Si è verificato un errore durante l\'impostazione dell\'ACL.',

        // System and Environment
        'imagick_not_available' => 'L\'estensione Imagick non è disponibile.',

        // Generic Error Categories
        'unexpected_error' => 'Errore imprevisto nel sistema.',
        'generic_server_error' => 'Si è verificato un errore generico del server.',
        'json_error' => 'Errore JSON nel dispatcher.',

        'fallback_error' => 'Si è verificato un errore, ma non è stata trovata alcuna configurazione specifica.',
        'fatal_fallback_failure' => 'Errore fatale: configurazione di fallback mancante. Il sistema non può rispondere correttamente.',
        'ucm_audit_not_found' => 'Nessun record di audit trovato per l\'ID configurazione indicato.',
    ],

    'user' => [
        // Authentication and Authorization
        'authentication_error' => 'Non hai l\'autorizzazione per eseguire questa operazione.',

        'ucm_delete_failed' => 'Si è verificato un errore durante l\'eliminazione della configurazione. Riprova più tardi.',
        'undefined_error_code' => 'Si è verificato un errore imprevisto. Contatta il supporto.',

        // File Validation
        'invalid_input' => 'Il valore fornito per :param non è valido. Controlla l\'input e riprova.',
        'invalid_image_structure' => 'L\'immagine che hai caricato non è valida. Prova con un\'altra immagine.',
        'mime_type_not_allowed' => 'Il tipo di file che hai caricato non è supportato. I tipi consentiti sono: :allowed_types.',
        'max_file_size' => 'Il file è troppo grande. La dimensione massima consentita è :max_size MB.',
        'invalid_file_extension' => 'L\'estensione del file non è supportata. Le estensioni consentite sono: :allowed_extensions.',
        'invalid_file_name' => 'Il nome del file contiene caratteri non validi. Usa solo lettere, numeri, spazi, trattini e underscore.',
        'invalid_file_pdf' => 'Il PDF caricato non è valido o potrebbe essere danneggiato.',

        // Virus and Security
        'virus_found' => 'Il file ":fileName" contiene minacce ed è stato bloccato per la tua sicurezza.',
        'scan_error' => 'Non è stato possibile verificare la sicurezza del file. Riprova più tardi.',

        // File Storage and IO
        'temp_file_not_found' => 'Problema riscontrato con il file :file',
        'file_not_found' => 'Il file richiesto non è stato trovato.',
        'error_getting_presigned_url' => 'Si è verificato un problema durante la preparazione dell\'upload. Riprova più tardi.',
        'error_during_file_upload' => 'Si è verificato un errore durante il caricamento. Riprova o contatta l\'assistenza se il problema persiste.',
        'generic_internal_error' => 'Si è verificato un errore interno. Il team tecnico è stato notificato.',
        'unable_to_save_bot_file' => 'Non è stato possibile salvare il file. Riprova più tardi.',
        'impossible_save_file' => 'Impossibile salvare il file. Riprova o contatta l\'assistenza.',

        // Database and Record Management
        'error_during_create_egi_record' => 'Si è verificato un errore durante il salvataggio nel database. Il team tecnico è stato informato.',

        // Security and Encryption
        'error_during_file_name_encryption' => 'Si è verificato un errore di sicurezza. Riprova più tardi.',
        'acl_setting_error' => 'Non è stato possibile impostare i permessi corretti sul file. Riprova o contatta l\'assistenza.',

        // System and Environment
        'imagick_not_available' => 'Il sistema non è configurato correttamente per elaborare le immagini. Contatta l\'amministratore.',

        // Generic Error Categories
        'unexpected_error' => 'Si è verificato un errore imprevisto. Il team tecnico è stato informato.',
        'generic_server_error' => 'Si è verificato un errore del server. Riprova più tardi o contatta l\'assistenza.',
        'json_error' => 'Si è verificato un errore di elaborazione dati. Riprova o contatta l\'assistenza.',

        'fallback_error' => 'Errore inatteso. Riprova più tardi.',
        'fatal_fallback_failure' => 'Errore grave inatteso. Contattare il supporto.',
        'ucm_audit_not_found' => 'Non ci sono informazioni di audit disponibili.',
    ],

    // Generic messages
    'generic_error' => 'Si è verificato un errore. Riprova più tardi.',
];
