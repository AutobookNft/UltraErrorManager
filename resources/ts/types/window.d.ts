// Esempio: In un file come 'resources/ts/types/window.d.ts'

// Dichiarazione globale per aumentare l'interfaccia Window standard
declare global {
    interface Window {
        /**
         * 🧱 Oggetto opzionale contenente le traduzioni passate dal backend (es. via Blade),
         *    organizzate per namespace (tipicamente 'js'). Utilizzato da UEM client-side
         *    per recuperare stringhe localizzate.
         */
        translations?: {
            /** Namespace per le traduzioni JavaScript. */
            js?: {
                /** Testo per pulsanti di conferma standard (usato da ErrorDisplayHandler). */
                ok_button?: string;

                /** Traduzioni specifiche per la gestione errori UEM (usato da ErrorDisplayHandler). */
                errors?: {
                    /** Mappa codici errore specifici ai loro titoli tradotti. */
                    titles?: Record<string, string>;
                    /** Titolo generico per errori critici/error. */
                    error_title?: string;
                    /** Titolo generico per avvisi (warning). */
                    warning_title?: string;
                    /** Titolo generico per avvisi informativi (notice). */
                    notice_title?: string;
                    // Aggiungere qui altre chiavi relative agli errori se necessario
                };

                /** Consente altre chiavi arbitrarie all'interno di 'js' senza errori TS. */
                [key: string]: any;
            };
            // Aggiungere altri namespace se necessario (es. window.phpVars)
        };

        /**
         * 📡 Funzione opzionale globale per mostrare notifiche toast.
         *    Referenziata da ErrorDisplayHandler come strategia di visualizzazione.
         *    L'implementazione deve essere fornita dall'applicazione principale.
         */
        showToast?: (message: string, type: 'info' | 'warning' | 'error' | 'success') => void;

        /**
         * ⚙️ Flag opzionale per indicare l'ambiente applicativo (impostato dal backend).
         *    Può essere usato per log condizionali client-side.
         */
        envMode?: 'local' | 'development' | 'staging' | 'production';

        /**
         * 🔑 Token CSRF opzionale se non si usa il meta tag standard.
         *    Referenziato da ErrorConfigLoader per le richieste API.
         */
        csrfToken?: string;

        // Aggiungere qui altre eventuali proprietà globali custom definite per 'window'
    }
}

// È necessaria un'esportazione (anche vuota) per trattare questo file come un modulo
// e rendere effettiva la dichiarazione globale nell'ambito del progetto.
export {};