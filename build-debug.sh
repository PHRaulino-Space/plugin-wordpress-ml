#!/bin/bash

# Build script para versões de debug
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
RANDOM_ID=$(openssl rand -hex 3)

echo "🔧 Build de versões DEBUG"
echo "⏰ Timestamp: $TIMESTAMP"

# Função para criar build individual
build_plugin() {
    local plugin_file=$1
    local plugin_name=$(basename "$plugin_file" .php)
    local build_name="${plugin_name}_${TIMESTAMP}_${RANDOM_ID}.zip"
    
    if [ ! -f "$plugin_file" ]; then
        echo "❌ Arquivo não encontrado: $plugin_file"
        return 1
    fi
    
    echo "📦 Criando: $build_name"
    
    # Criar zip apenas com o arquivo PHP
    zip -q "$build_name" "$plugin_file"
    
    if [ $? -eq 0 ]; then
        local file_size=$(stat -f%z "$build_name" 2>/dev/null || stat -c%s "$build_name" 2>/dev/null)
        local size_kb=$((file_size / 1024))
        echo "✅ $build_name criado (${size_kb}KB)"
    else
        echo "❌ Erro ao criar $build_name"
        return 1
    fi
}

# Builds individuais para cada versão de debug
echo ""
echo "🚀 Criando builds das versões DEBUG..."

build_plugin "mercadolivre-debug.php"
build_plugin "mercadolivre-db-test.php"
build_plugin "mercadolivre-safe.php"
build_plugin "mercadolivre-integration-minimal.php"
build_plugin "mercadolivre-integration-no-assets.php"

echo ""
echo "📋 Arquivos criados:"
ls -la *_${TIMESTAMP}_${RANDOM_ID}.zip 2>/dev/null

echo ""
echo "🎯 RECOMENDAÇÃO DE TESTE:"
echo "1️⃣  Primeiro: mercadolivre-debug_${TIMESTAMP}_${RANDOM_ID}.zip"
echo "2️⃣  Se OK: mercadolivre-db-test_${TIMESTAMP}_${RANDOM_ID}.zip"
echo "3️⃣  Se OK: mercadolivre-safe_${TIMESTAMP}_${RANDOM_ID}.zip"
echo "4️⃣  Se OK: mercadolivre-integration-no-assets_${TIMESTAMP}_${RANDOM_ID}.zip"
echo ""
echo "✨ Builds DEBUG finalizados!"