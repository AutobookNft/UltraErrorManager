#!/bin/bash

# Directory di output per i file generati
output_dir="/home/fabio/libraries/UEMCompiled"

# Nomi dei file di output divisi per categoria
server_file="$output_dir/UEM_server_code.txt"
client_file="$output_dir/UEM_client_code.txt"
config_file="$output_dir/UEM_config_misc.txt"

log_file="$output_dir/UEM_compilation_log.txt"
timestamp=$(date +"%Y%m%d%H%M%S")
zip_file="$output_dir/UEM_compiled_$timestamp.zip"
hash_file="$output_dir/UEM_file_hashes.txt"
prev_hash_file="$output_dir/UEM_file_hashes_prev.txt"
shared_dir="/var/www/shared"
modified_files_file="$shared_dir/UEM_modified_files.txt"

# Directory target specifica per UEM - PERCORSO AGGIORNATO
target_dir="/home/fabio/libraries/UltraErrorManager"

# Assicurati che le directory esistano
mkdir -p "$output_dir"
mkdir -p "$shared_dir"

# Backup degli hash precedenti
if [ -f "$hash_file" ]; then
    mv "$hash_file" "$prev_hash_file"
fi

# Funzione per generare hash dei file inclusi
generate_hashes() {
    echo "### Hash dei File Inclusi in UEM ###" > "$hash_file"
    find "$target_dir" \
        -type d \( -path "*/vendor" -o -path "*/node_modules" -o -path "*/.*" -o -path "*/storage/framework" -o -path "*/public" \) -prune -o \
        -type f \( -name "*.php" -o -name "*.ts" -o -name "*.js" -o -name "*.env" -o -name "*.config.js" -o -name "*.blade.php" -o -name "*.css" -o -name "*.ico" -o -name "*.txt" \) -print | sort | while read -r file; do
            hash=$(sha256sum "$file" | awk '{print $1}')
            echo "$file: $hash" >> "$hash_file"
        done
}

# Funzione per confrontare gli hash
compare_hashes() {
    if [ -f "$prev_hash_file" ]; then
        echo "### File Modificati in UEM ###" > "$modified_files_file"
        while IFS= read -r line; do
            current_file=$(echo "$line" | awk -F": " '{print $1}')
            current_hash=$(echo "$line" | awk -F": " '{print $2}')
            prev_hash=$(grep -F "$current_file" "$prev_hash_file" | awk -F": " '{print $2}')
            if [ "$current_hash" != "$prev_hash" ]; then
                echo "$current_file" >> "$modified_files_file"
            fi
        done < "$hash_file"
    else
        echo "Nessun file hash precedente trovato per UEM." > "$modified_files_file"
    fi
}

# Inizia il log
echo "Inizio compilazione UEM: $(date)" > "$log_file"
server_count=0
client_count=0
config_count=0
excluded_count=0
excluded_files=()

# Verifica che la directory target esista
if [ ! -d "$target_dir" ]; then
    echo "Errore: la directory $target_dir non esiste!" | tee -a "$log_file"
    exit 1
fi

# Inizializza i file di output
echo -e "######## Ultra Error Manager (UEM) - Codice Server - Generato il $(date) ########\n" > "$server_file"
echo -e "######## Ultra Error Manager (UEM) - Codice Client - Generato il $(date) ########\n" > "$client_file"
echo -e "######## Ultra Error Manager (UEM) - Configurazioni e Altro - Generato il $(date) ########\n" > "$config_file"

# Funzione per aggiungere un file al file di output appropriato
add_file_to_output() {
    local file=$1
    local output=$2
    local category=$3
    
    # Aggiungi separatore con percorso completo
    echo -e "\n######## File: $file ########\n" >> "$output"
    # Aggiungi contenuto del file
    cat "$file" >> "$output"
    echo -e "\n" >> "$output" # Linea vuota per separazione
    echo "File aggiunto a $category: $file" >> "$log_file"
    
    # Incrementa il contatore appropriato
    if [ "$category" == "Server" ]; then
        ((server_count++))
    elif [ "$category" == "Client" ]; then
        ((client_count++))
    else
        ((config_count++))
    fi
}

# Elabora tutti i file rilevanti
find "$target_dir" \
    -type d \( -path "*/vendor" -o -path "*/node_modules" -o -path "*/.*" -o -path "*/storage/framework" -o -path "*/public" \) -prune -o \
    -type f \( -name "*.php" -o -name "*.ts" -o -name "*.js" -o -name "*.env" -o -name "*.config.js" -o -name "*.blade.php" -o -name "*.css" -o -name "*.ico" -o -name "*.txt" \) -print | sort | while read -r file; do
        if [[ $file == *"vendor"* || $file == *"node_modules"* || $file == *"/.*"* || $file == *"storage/framework"* || $file == *"public"* ]]; then
            excluded_files+=("$file")
            ((excluded_count++))
            continue
        fi

        # Classifica i file in base all'estensione e al percorso
        if [[ $file == *".php" && $file != *".blade.php" ]]; then
            # File PHP è lato server (escludi le viste Blade)
            add_file_to_output "$file" "$server_file" "Server"
        elif [[ $file == *".blade.php" ]]; then
            # Viste Blade vanno nella configurazione/altro
            add_file_to_output "$file" "$config_file" "Config"
        elif [[ $file == *".js" || $file == *".ts" || $file == *".css" ]]; then
            # JavaScript, TypeScript e CSS sono lato client
            add_file_to_output "$file" "$client_file" "Client"
        else
            # Tutti gli altri file vanno nella configurazione/altro
            add_file_to_output "$file" "$config_file" "Config"
        fi
    done

# Genera hash dei file
generate_hashes
# Confronta gli hash con la versione precedente
compare_hashes

# Aggiungi i file esclusi al log
echo -e "\nFile esclusi da UEM:" >> "$log_file"
for file in "${excluded_files[@]}"; do
    echo "$file" >> "$log_file"
done

# Riepilogo nel log
echo -e "\nTotale file server processati: $server_count" >> "$log_file"
echo -e "Totale file client processati: $client_count" >> "$log_file"
echo -e "Totale file config/misc processati: $config_count" >> "$log_file"
echo -e "Totale file esclusi: $excluded_count" >> "$log_file"
echo "Fine compilazione UEM: $(date)" >> "$log_file"

# Comprimi tutti i file in un archivio .zip
zip -q "$zip_file" "$server_file" "$client_file" "$config_file" "$log_file" "$hash_file" "$modified_files_file"
echo "File compressi in: $zip_file" >> "$log_file"

echo "Compilazione UEM completata!"
echo "File server: $server_file"
echo "File client: $client_file"
echo "File config: $config_file"
echo "Log: $log_file"
echo "Archivio: $zip_file"
echo "Hashes: $hash_file"
echo "Modifiche: $modified_files_file"