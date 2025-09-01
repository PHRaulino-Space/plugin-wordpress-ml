Requisitos do Projeto: Plugin de Sincronização do Mercado Livre para WordPress
Visão Geral
Este projeto consiste na criação de um plugin para WordPress que permite ao dono do site sincronizar produtos de sua conta do Mercado Livre e exibi-los em um catálogo no próprio site. O plugin terá uma página de configuração no WP-Admin para credenciais e uma página pública (para usuários logados) que exibirá a tabela de produtos.

1. Requisitos do WP-Admin
Página de Configuração:

Deve haver uma nova página de menu no painel de administração do WordPress.

Esta página deve conter um formulário para o dono do site inserir e salvar suas credenciais da API do Mercado Livre.

Os campos obrigatórios são: App ID, Secret Key e Redirect URL.

2. Requisitos da Área do Usuário (Front-end)
Página do Catálogo de Produtos:

A página deve ser acessível apenas para usuários logados.

Deve exibir uma tabela com todos os produtos sincronizados.

A tabela deve mostrar as informações relevantes do produto (e.g., título, preço, etc.).

Funcionalidades de Interação:

Botão de Autenticação: Deve haver um botão para redirecionar o usuário para a página de login do Mercado Livre para obter a autorização inicial e o access_token.

Botão de Sincronização: Após a autenticação, um botão de "Sincronizar Produtos" deve estar disponível para iniciar o processo de coleta dos dados do Mercado Livre.

Gestão de Produtos:

Na tabela, o usuário deve poder marcar um produto como visível (utilizando um checkbox ou toggle).

Deve ser possível adicionar "tags" (categorias) a cada produto na tabela, permitindo um relacionamento de "muitos para muitos".

3. Requisitos de Backend
Autenticação com o Mercado Livre:

O plugin deve ser capaz de gerenciar o fluxo de autenticação OAuth 2.0 do Mercado Livre.

Deve ser capaz de usar o redirect_url para receber o code de autorização.

O code deve ser trocado por um access_token e um refresh_token através de uma requisição à API do Mercado Livre.

Os tokens devem ser salvos de forma segura para o usuário logado (e.g., como user_meta).

Sincronização de Dados:

A lógica de sincronização deve ser iniciada pelo botão no front-end.

Deve utilizar o access_token do usuário para fazer requisições à API.

O processo deve:

Pesquisar todos os produtos listados pelo usuário no Mercado Livre.

Iterar sobre cada produto para obter os detalhes completos através de uma segunda requisição.

Salvar os dados detalhados de cada produto e suas imagens no banco de dados do WordPress.

Lógica de Negócios:

Listar Produtos: Uma função para listar todos os produtos do banco de dados, com a opção de filtrar pelos produtos visíveis.

Filtragem e Pesquisa: Funções para permitir a pesquisa e o filtro dos produtos por tags (categorias).

4. Requisitos do Banco de Dados
O plugin deve criar as seguintes tabelas personalizadas na ativação:

Tabela de Produtos (ml_products):

Campos: id (chave primária), ml_id (ID do Mercado Livre), title, price, thumbnail_url, permalink e visible (booleano).

Tabela de Imagens (ml_product_images):

Campos: id (chave primária), product_id (chave estrangeira para ml_products.id), url.

Tabela de Categorias (ml_categories):

Campos: id (chave primária), name.

Tabela de Relacionamento (ml_product_categories):

Tabela pivô para o relacionamento "muitos para muitos" entre produtos e categorias.

Campos: product_id, category_id.

5. Requisitos Adicionais
Persistência de Dados: O plugin deve garantir que os dados dos produtos, categorias e imagens sejam salvos de forma persistente e recuperáveis a cada sincronização.

Mensagens de Status: Exibir mensagens de sucesso ou erro no front-end para informar o usuário sobre o status da sincronização.

## Regras de Sincronização (Implementadas)

### Fluxo de Sincronização Completa

A sincronização segue um fluxo específico para garantir consistência dos dados:

1. **Limpeza Seletiva de Dados:**
   - Remove todos os produtos da tabela `ml_products`
   - Remove todas as imagens da tabela `ml_product_images`  
   - Remove todos os relacionamentos da tabela `ml_product_categories`
   - **MANTÉM:** Histórico de categorias (`ml_categories`) e preferências de visibilidade (`ml_product_visibility`)

2. **Sincronização com Mercado Livre:**
   - Busca todos os produtos ativos do usuário na API do ML
   - Para cada produto, obtém detalhes completos
   - Insere produtos, imagens e relaciona com categorias
   - Mantém categorias existentes usando `ml_category_id` como chave primária

3. **Preservação de Configurações:**
   - **Categorias:** Usar ID do ML como chave primária para manter histórico
   - **Visibilidade:** Separada em tabela própria por usuário (`ml_product_visibility`)
   - **Relacionamentos:** Recriados a cada sync baseados nos dados atuais do ML

### Estrutura do Banco Atualizada

**Tabela `ml_products` (dados do ML - renovada a cada sync):**
```sql
- id (AUTO_INCREMENT)
- ml_id (UNIQUE - ID do Mercado Livre)
- title, price, thumbnail_url, permalink
- created_at, updated_at
```

**Tabela `ml_categories` (histórico mantido):**
```sql
- ml_category_id (PRIMARY KEY - ID do ML)
- name, path_from_root
- created_at
```

**Tabela `ml_product_visibility` (preferências do usuário):**
```sql
- ml_id (ID do produto no ML)  
- user_id (usuário WordPress)
- visible (preferência de visibilidade)
- created_at, updated_at
- PRIMARY KEY (ml_id, user_id)
```

**Tabela `ml_product_categories` (relacionamento):**
```sql
- product_id (FK para ml_products.id)
- ml_category_id (FK para ml_categories.ml_category_id)
```

### Benefícios desta Implementação

1. **Dados sempre atualizados:** Sincronização completa remove inconsistências
2. **Configurações preservadas:** Visibilidade e categorias mantidas entre syncs
3. **Performance:** Limpeza + inserção é mais rápida que update seletivo
4. **Consistência:** Garante que apenas produtos ativos no ML estejam no sistema
5. **Flexibilidade:** Permite mudanças na estrutura de dados do ML sem conflitos
