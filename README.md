# Relatório Técnico - EN 50131

Plataforma web para geração de relatórios técnicos de instalação e manutenção de sistemas de alarme e CCTV, conforme a norma EN 50131.

## Funcionalidades

- ✅ Checklist completa EN 50131 (5 secções, 15 itens)
- ✅ Autenticação de técnicos (admin/técnico)
- ✅ Gestão de clientes
- ✅ Histórico de relatórios com pesquisa
- ✅ Geração de PDF profissional
- ✅ Minuta imprimível para preenchimento manual offline

## Stack

- PHP 8.x
- MySQL 8.0
- Dompdf (geração de PDF)
- Apache

## Instalação

1. Colocar os ficheiros no servidor web
2. Configurar `config/database.php` com as credenciais MySQL
3. Importar o schema SQL manualmente ou a BD é criada pelo install.php

## Credenciais Padrão

- **Admin:** admin / admin123
- **Técnico:** tecnico / tecnico123

## Estrutura

```
relatorio_tecnico/
├── config/database.php
├── includes/auth.php, header.php, footer.php
├── assets/css/style.css
├── assets/js/script.js
├── login.php, dashboard.php
├── novo_relatorio.php, ver_relatorio.php
├── relatorio_pdf.php, minuta.php
├── historico.php, clientes.php, utilizadores.php
└── vendor/ (Dompdf)
```
