#!/bin/bash

# Percorsi delle cartelle di backup
backup_root="/mnt/c/wsl_backup"  # Directory radice esistente
backup_base_dir="${backup_root}/EGI_backup"  # Sottodirectory per tutti i backup
timestamp=$(date +%Y%m%d_%H%M)
backup_dest_c="${backup_base_dir}/UEM_${timestamp}"  # Sottodirectory specifica per UEM
LOG_FILE="/home/fabio/libraries/UEMCompiled/UEM_backup_log.txt"

# Directory sorgente (intero progetto UltraErrorManager)
source_dir="/home/fabio/libraries/UltraErrorManager"

# Funzione per verificare se un drive è disponibile
check_drive() {
    local drive_path="$1"
    local drive_letter="$2"
    if [ ! -d "$(dirname "$drive_path")" ]; then
        echo "ATTENZIONE: Drive $drive_letter non disponibile, skip della copia" >> "$LOG_FILE"
        return 1
    fi
    return 0
}

# Percorsi delle cartelle di backup aggiuntive
drive_d="/mnt/d/Il mio Drive/EGI_backup/UEM_${timestamp}"
drive_e="/mnt/e/EGI_backup/UEM_${timestamp}"
drive_h="/mnt/h/EGI_backup/UEM_${timestamp}"

# Directory da escludere dal backup
exclude_dirs=(
    "--exclude=vendor/"  # Esclude la directory vendor come richiesto
    "--exclude=.git/"    # Esclude directory nascoste come .git (standard)
)

# Inizio log operazione
mkdir -p "$(dirname "$LOG_FILE")"  # Crea la directory del log se non esiste
echo "===== Inizio backup UEM $(date) =====" >> "$LOG_FILE"

# Verifica e creazione directory principale
if [ ! -d "$backup_root" ]; then
    echo "ERRORE: Directory radice di backup non accessibile: $backup_root" >> "$LOG_FILE"
    exit 1
fi

# Crea la sottodirectory EGI_backup se non esiste
if [ ! -d "$backup_base_dir" ]; then
    mkdir -p "$backup_base_dir"
    if [ $? -ne 0 ]; then
        echo "ERRORE: Impossibile creare la directory di backup: $backup_base_dir" >> "$LOG_FILE"
        exit 1
    fi
    echo "Creata directory di backup: $backup_base_dir" >> "$LOG_FILE"
fi

mkdir -p "$backup_dest_c"

# Verifica che la directory sorgente esista
if [ ! -d "$source_dir" ]; then
    echo "ERRORE: Directory sorgente non trovata: $source_dir" >> "$LOG_FILE"
    exit 1
fi

# Comando rsync principale
if rsync -avz "${exclude_dirs[@]}" "$source_dir/" "$backup_dest_c"; then
    echo "✓ Backup principale UEM completato in: $backup_dest_c" >> "$LOG_FILE"
else
    echo "✗ ERRORE nel backup principale UEM!" >> "$LOG_FILE"
    exit 1
fi

# Copia nelle altre destinazioni
# Drive D
if check_drive "$drive_d" "D:"; then
    mkdir -p "$drive_d"
    if rsync -av "$backup_dest_c/" "$drive_d"; then
        echo "✓ Copia UEM su D: completata in $drive_d" >> "$LOG_FILE"
    else
        echo "✗ ERRORE nella copia UEM su D:" >> "$LOG_FILE"
    fi
fi

# Drive E
if check_drive "$drive_e" "E:"; then
    mkdir -p "$drive_e"
    if rsync -av "$backup_dest_c/" "$drive_e"; then
        echo "✓ Copia UEM su E: completata in $drive_e" >> "$LOG_FILE"
    else
        echo "✗ ERRORE nella copia UEM su E:" >> "$LOG_FILE"
    fi
fi

# Drive H
if check_drive "$drive_h" "H:"; then
    mkdir -p "$drive_h"
    if rsync -av "$backup_dest_c/" "$drive_h"; then
        echo "✓ Copia UEM su H: completata in $drive_h" >> "$LOG_FILE"
    else
        echo "✗ ERRORE nella copia UEM su H:" >> "$LOG_FILE"
    fi
fi

# Fine log operazione
echo "===== Fine backup UEM $(date) =====" >> "$LOG_FILE"