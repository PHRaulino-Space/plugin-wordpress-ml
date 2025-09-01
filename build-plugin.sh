#!/bin/bash

# Define o nome do diretório fonte e do arquivo de saída.
SRC_DIR="mercadolivre-integration"
OUTPUT_ZIP="${SRC_DIR}.zip"

echo "🔨 Iniciando build do plugin..."

# Verifica se o diretório fonte existe.
if [ ! -d "$SRC_DIR" ]; then
    echo "❌ ERRO: Diretório fonte '${SRC_DIR}' não encontrado!"
    exit 1
fi

# Remove o arquivo .zip antigo, se existir, para evitar duplicatas.
echo "🧹 Limpando build antigo..."
rm -f "$OUTPUT_ZIP"

# Navega para o diretório pai, cria o zip com o conteúdo do diretório do plugin.
# O nome da pasta principal dentro do zip será o nome do SRC_DIR.
echo "📦 Compactando o diretório '${SRC_DIR}' em '${OUTPUT_ZIP}'..."
zip -r "$OUTPUT_ZIP" "$SRC_DIR"

# Verifica se o zip foi criado com sucesso.
if [ $? -eq 0 ] && [ -f "$OUTPUT_ZIP" ]; then
    FILE_SIZE=$(stat -f%z "$OUTPUT_ZIP" 2>/dev/null || stat -c%s "$OUTPUT_ZIP" 2>/dev/null)
    FILE_SIZE_KB=$((FILE_SIZE / 1024))
    
    echo ""
    echo "🎉 BUILD CONCLUÍDO COM SUCESSO!"
    echo "📁 Arquivo: $OUTPUT_ZIP"
    echo "📏 Tamanho: ${FILE_SIZE_KB}KB"
    
    echo ""
    echo "🧪 Testando integridade do arquivo..."
    if zip -T "$OUTPUT_ZIP" > /dev/null 2>&1; then
        echo "✅ Arquivo zip íntegro"
    else
        echo "❌ ERRO: Arquivo zip corrompido!"
        exit 1
    fi
else
    echo "❌ ERRO: Falha ao criar o arquivo zip"
    exit 1
fi

echo ""
echo "✨ Build finalizado: $OUTPUT_ZIP"
