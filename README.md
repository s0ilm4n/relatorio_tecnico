# Relatório Técnico — EN 50131

Plataforma web para geração de **relatórios técnicos** de instalação e manutenção de sistemas de **alarme** e **CCTV**, conforme a **norma EN 50131** (Graus 1 a 4).

Criada para técnicos no terreno: gera PDF profissional para entregar ao cliente **e** minuta imprimível para quando não há internet.

---

## Funcionalidades

- ✅ **Checklist EN 50131 completa** — 5 secções, 15 itens (inspeção visual, ensaios elétricos, testes funcionais, CRA, encerramento)
- ✅ **Campos para valores medidos** — tensões, resistências, testes de bateria, canais CRA
- ✅ **Autenticação** — dois níveis: admin e técnico
- ✅ **Gestão de clientes** — associar múltiplos relatórios ao mesmo cliente
- ✅ **Geração de PDF** — layout profissional A4, 3 páginas, pronto a entregar
- ✅ **Minuta offline** — versão em papel para preenchimento manual em obra
- ✅ **Histórico** — pesquisa, paginação, exportação
- ✅ **Dashboard** — estatísticas, acesso rápido aos últimos relatórios
- ✅ **Norma EN 50131** — campos específicos para Grau 1, 2, 3 e 4

---

## Stack

| Componente | Tecnologia |
|---|---|
| Backend | **PHP 8.x** |
| Base de Dados | **MySQL 8.0** (ou MariaDB 10.5+) |
| PDF | **Dompdf 3.x** |
| Servidor Web | **Apache 2.4** com mod_rewrite |
| Frontend | HTML5 + CSS3 + JavaScript vanilla |

---

## Instalação

### 1. Pré-requisitos

- Servidor Linux com Apache + PHP 8.0+ + MySQL/MariaDB
- Extensões PHP: `pdo_mysql`, `mbstring`, `gd`, `xml`, `curl`
- Composer (para gerir dependências PHP)

Verificar extensões:
```bash
php -m | grep -E 'pdo_mysql|mbstring|gd|xml|curl'
```

### 2. Obter o código

```bash
cd /var/www/html
git clone https://github.com/s0ilm4n/relatorio_tecnico.git
cd relatorio_tecnico
```

Ou descarregar o ZIP e extrair para `/var/www/html/relatorio_tecnico/`.

### 3. Instalar dependências PHP (Dompdf)

```bash
cd /var/www/html/relatorio_tecnico
composer install --no-dev
```

### 4. Configurar a base de dados

Criar a base de dados e importar as tabelas:

```bash
mysql -u root -p -e "CREATE DATABASE relatorio_tecnico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p relatorio_tecnico < schema.sql
```

**Nota:** O schema.sql não existe por omissão — a BD é criada automaticamente pelo script `install.php`. Basta aceder a:

```
http://SEU_IP/relatorio_tecnico/install.php
```

Ou, em alternativa, executar manualmente os comandos SQL do ficheiro `config/schema.sql` (se existir).

### 5. Configurar acesso à BD

Editar `config/database.php` com as credenciais MySQL:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // ← o teu user MySQL
define('DB_PASS', 'password');    // ← a tua password MySQL
define('DB_NAME', 'relatorio_tecnico');
```

### 6. Permissões

O Apache precisa de escrita na pasta de sessões (geralmente já configurada). Dar permissões ao projecto:

```bash
sudo chown -R www-data:www-data /var/www/html/relatorio_tecnico
sudo chmod -R 755 /var/www/html/relatorio_tecnico
```

### 7. Configurar Apache (se necessário)

Se o projecto estiver dentro de `/var/www/html/`, o Apache já deve servir automaticamente. Caso contrário, adicionar ao VirtualHost:

```apache
<Directory /caminho/para/relatorio_tecnico>
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

### 8. Aceder

```
http://SEU_IP/relatorio_tecnico/
```

---

## Credenciais Padrão

| Utilizador | Password | Função |
|---|---|---|
| `admin` | `admin123` | Administrador |
| `tecnico` | `tecnico123` | Técnico |

**Altera a password do admin após o primeiro login!**

---

## Estrutura do Projecto

```
relatorio_tecnico/
├── config/
│   └── database.php          # Configuração da BD
├── includes/
│   ├── auth.php              # Autenticação e sessão
│   ├── header.php            # Header HTML + navbar
│   └── footer.php            # Footer HTML
├── assets/
│   ├── css/
│   │   └── style.css         # Estilos completos (web + print)
│   └── js/
│       └── script.js         # Validações e auto-preenchimento
├── vendor/                   # Dependências (Dompdf via Composer)
├── login.php                 # Página de login
├── dashboard.php             # Dashboard com estatísticas
├── novo_relatorio.php        # Formulário da checklist
├── ver_relatorio.php         # Visualizar relatório
├── relatorio_pdf.php         # Gerar PDF (Dompdf)
├── minuta.php                # Minuta imprimível para papel (offline)
├── historico.php             # Histórico com pesquisa
├── clientes.php              # Gestão de clientes (admin)
├── utilizadores.php          # Gestão de utilizadores (admin)
├── eliminar_relatorio.php    # Eliminar relatório (admin)
├── index.php                 # Redirecciona para login
├── logout.php                # Terminar sessão
├── composer.json             # Dependências PHP
└── .gitignore
```

---

## Como Usar

### Criar um relatório

1. Fazer login com credenciais de técnico ou admin
2. Clicar em **"+ Novo Relatório"**
3. Preencher dados do cliente (selecionar existente ou criar novo)
4. Preencher dados do relatório (data, central, grau, etc.)
5. Percorrer a checklist e assinalar itens verificados
6. Inserir valores medidos (tensões, etc.) nos campos próprios
7. Adicionar notas e material substituído
8. Clicar em **"Guardar Relatório"**

### Gerar PDF

- Na página de visualização do relatório, clicar em **"📄 Descarregar PDF"**
- O PDF é gerado em A4 com cabeçalho, checklist, assinaturas

### Minuta para papel (offline)

- Clicar em **"🖨️ Minuta"** — abre uma versão limpa e imprimível
- Se o relatório já existe, a minuta aparece preenchida
- Se o URL for acedido sem ID, mostra uma versão em branco para preencher à mão
- Perfeito para levar para obra impressa

---

## Segurança

- As passwords são guardadas com `password_hash()` (bcrypt)
- Sessões PHP com proteção contra session fixation
- Apenas admin pode gerir utilizadores e clientes
- O ficheiro `config/database.php` não é acessível via web (fora do DocumentRoot) ou protegido por `.htaccess`

---

## Licença

Uso livre para fins profissionais e educacionais.
