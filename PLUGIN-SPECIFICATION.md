# Plugin WordPress - Integração Mercado Livre

## Visão Geral

Este plugin permite a integração completa entre WordPress e a API do Mercado Livre, oferecendo funcionalidades para importar, gerenciar e exibir produtos do Mercado Livre no seu site WordPress.

## Funcionalidades Principais

### 1. Conexão com API do Mercado Livre
- Autenticação OAuth2 com Mercado Livre
- Configuração de credenciais (App ID, Secret Key)
- Gerenciamento de tokens de acesso
- Renovação automática de tokens

### 2. Importação de Produtos
- Busca de produtos por categoria, palavra-chave ou seller
- Importação em lote de produtos
- Sincronização automática de dados
- Atualização de preços e estoque em tempo real

### 3. Gerenciamento de Catálogo
- Listagem de produtos importados
- Controle de visibilidade (Show/Hide) individual
- Organização por categorias personalizadas
- Sistema de tags e filtros

### 4. Gestão de Imagens
- Download automático de imagens dos produtos
- Armazenamento otimizado no WordPress Media Library
- Múltiplas imagens por produto
- Redimensionamento automático

### 5. Interface Administrativa
- Dashboard com estatísticas
- Configurações do plugin
- Logs de sincronização
- Sistema de notificações

## Estrutura do Banco de Dados

### Tabela: wp_ml_products
```sql
CREATE TABLE wp_ml_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ml_id VARCHAR(255) UNIQUE NOT NULL,
    title TEXT NOT NULL,
    description LONGTEXT,
    price DECIMAL(10,2) NOT NULL,
    original_price DECIMAL(10,2),
    currency_id VARCHAR(10) DEFAULT 'BRL',
    available_quantity INT DEFAULT 0,
    sold_quantity INT DEFAULT 0,
    condition_type ENUM('new', 'used') DEFAULT 'new',
    permalink VARCHAR(500),
    thumbnail VARCHAR(500),
    category_id VARCHAR(255),
    seller_id VARCHAR(255),
    status ENUM('active', 'paused', 'closed') DEFAULT 'active',
    is_visible BOOLEAN DEFAULT TRUE,
    wp_category_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_sync TIMESTAMP,
    INDEX idx_ml_id (ml_id),
    INDEX idx_status (status),
    INDEX idx_visible (is_visible),
    INDEX idx_category (wp_category_id)
);
```

### Tabela: wp_ml_product_images
```sql
CREATE TABLE wp_ml_product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    wp_attachment_id INT,
    image_order INT DEFAULT 0,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES wp_ml_products(id) ON DELETE CASCADE,
    INDEX idx_product_id (product_id),
    INDEX idx_order (image_order)
);
```

### Tabela: wp_ml_categories
```sql
CREATE TABLE wp_ml_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ml_category_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    path_from_root JSON,
    parent_id VARCHAR(255),
    wp_category_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ml_category (ml_category_id),
    INDEX idx_wp_category (wp_category_id)
);
```

### Tabela: wp_ml_sync_logs
```sql
CREATE TABLE wp_ml_sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_type ENUM('manual', 'automatic', 'scheduled') NOT NULL,
    status ENUM('running', 'completed', 'failed') NOT NULL,
    products_processed INT DEFAULT 0,
    products_updated INT DEFAULT 0,
    products_created INT DEFAULT 0,
    errors_count INT DEFAULT 0,
    error_messages TEXT,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_type (sync_type)
);
```

### Tabela: wp_ml_settings
```sql
CREATE TABLE wp_ml_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    autoload BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_autoload (autoload)
);
```

## Fluxos de Funcionamento

### Fluxo 1: Configuração Inicial
```
1. Instalação do Plugin
2. Ativação e criação das tabelas
3. Configuração de credenciais ML
4. Teste de conexão
5. Configurações gerais do plugin
```

### Fluxo 2: Importação de Produtos
```
1. Usuário acessa página de importação
2. Define critérios de busca (categoria/palavra-chave)
3. Plugin consulta API do Mercado Livre
4. Exibe prévia dos produtos encontrados
5. Usuário seleciona produtos para importar
6. Plugin processa importação:
   - Salva dados do produto
   - Download das imagens
   - Vincula categorias
   - Registra log
7. Notifica usuário sobre status
```

### Fluxo 3: Sincronização Automática
```
1. Cron job executa verificação
2. Lista produtos que precisam sincronizar
3. Para cada produto:
   - Consulta API ML para dados atuais
   - Compara com dados locais
   - Atualiza se houver diferenças
   - Registra log de mudanças
4. Envia relatório por email (opcional)
```

### Fluxo 4: Exibição no Frontend
```
1. Usuário navega pelo site
2. WordPress carrega produtos ML
3. Plugin aplica filtros de visibilidade
4. Renderiza produtos com:
   - Dados atualizados
   - Imagens otimizadas
   - Links para Mercado Livre
   - Informações de categoria
```

## Estrutura de Arquivos do Plugin

```
wp-content/plugins/mercadolivre-integration/
├── mercadolivre-integration.php          # Arquivo principal
├── includes/
│   ├── class-ml-core.php                 # Classe principal
│   ├── class-ml-api.php                  # Integração com API
│   ├── class-ml-database.php             # Operações de BD
│   ├── class-ml-admin.php                # Interface admin
│   ├── class-ml-frontend.php             # Exibição frontend
│   ├── class-ml-sync.php                 # Sincronização
│   └── class-ml-installer.php            # Instalação/Ativação
├── admin/
│   ├── css/
│   ├── js/
│   └── views/
│       ├── dashboard.php
│       ├── products.php
│       ├── import.php
│       ├── categories.php
│       └── settings.php
├── public/
│   ├── css/
│   └── js/
├── templates/
│   ├── product-list.php
│   ├── product-single.php
│   └── category-grid.php
└── languages/
    ├── mercadolivre-integration-pt_BR.po
    └── mercadolivre-integration-pt_BR.mo
```

## APIs e Integrações

### Endpoints do Mercado Livre Utilizados

1. **Autenticação**
   - `POST /oauth/token` - Obter token de acesso
   - `POST /oauth/token` - Renovar token

2. **Produtos**
   - `GET /items/{item_id}` - Detalhes do produto
   - `GET /sites/{site_id}/search` - Buscar produtos
   - `GET /items/{item_id}/descriptions` - Descrição do produto

3. **Categorias**
   - `GET /sites/{site_id}/categories` - Listar categorias
   - `GET /categories/{category_id}` - Detalhes da categoria

4. **Usuários/Sellers**
   - `GET /users/{user_id}` - Informações do vendedor
   - `GET /users/{user_id}/items/search` - Produtos do vendedor

## Configurações do Plugin

### Configurações de API
- **App ID**: ID da aplicação no Mercado Livre
- **Secret Key**: Chave secreta da aplicação
- **Site ID**: Identificador do país (MLB para Brasil)
- **Redirect URI**: URL de retorno para OAuth

### Configurações de Sincronização
- **Intervalo de Sync**: Frequência de sincronização automática
- **Produtos por Lote**: Quantidade de produtos processados por vez
- **Timeout API**: Tempo limite para requisições
- **Retry Attempts**: Tentativas de reprocessamento em caso de erro

### Configurações de Exibição
- **Template Padrão**: Layout para exibição dos produtos
- **Imagens por Produto**: Número máximo de imagens
- **Redimensionamento**: Tamanhos de imagem automáticos
- **Cache**: Tempo de cache para dados do ML

## Recursos Avançados

### Sistema de Cache
- Cache de consultas à API
- Cache de imagens processadas
- Invalidação automática baseada em TTL

### Logs e Monitoramento
- Log detalhado de todas as operações
- Métricas de performance
- Alertas de erro por email

### Webhooks (Futuro)
- Notificações automáticas de mudanças
- Sincronização em tempo real
- Redução de consultas à API

### Import/Export
- Exportação de dados para CSV/XML
- Importação em lote via arquivo
- Backup automático das configurações

## Requisitos Técnicos

### WordPress
- Versão mínima: 5.0
- PHP: 7.4 ou superior
- MySQL: 5.7 ou superior

### Permissões
- Criação de tabelas no banco
- Upload de arquivos (imagens)
- Execução de cron jobs
- Requisições HTTP externas

### Dependências
- cURL ativado
- JSON extension
- GD/ImageMagick para processamento de imagens