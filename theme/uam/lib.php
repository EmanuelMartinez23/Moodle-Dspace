<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Injecta SCSS adicional para el tema UAM.
 *
 * @param theme_config $theme
 * @return string SCSS a inyectar al final del CSS principal.
 */
function theme_uam_get_extra_scss($theme): string {
    // SCSS adicional del tema (usar nowdoc para evitar interpolación PHP).
    $scss = <<<'SCSS'
/*
 UAM Lerma – Identidad visual para Moodle 4.4
 Colores institucionales como variables CSS (utilizables por administradores y para estados):
*/
:root {
  --uam-lerma: #AD25A8;
  --uam-black: #000000;
  --uam-white: #ffffff;
  --uam-gray-900: #212529;
  --uam-gray-700: #495057;
  --uam-gray-100: #f8f9fa;

  /* Derivados para UI accesible */
  --uam-primary: var(--uam-lerma);
  --uam-primary-contrast: #fff;
  --uam-secondary: var(--uam-gray-700);
  --uam-secondary-contrast: #fff;
  --uam-focus: var(--uam-lerma);
}

/* Variables SCSS para cálculos de color en tiempo de compilación */
$uam-primary: #AD25A8;
$uam-black: #000000;
$uam-white: #ffffff;
$uam-gray-900: #212529;
$uam-gray-700: #495057;
$uam-gray-100: #f8f9fa;

/* Superficies base para modo oscuro (definir antes de cualquier uso) */
$uam-surface-dark: lighten($uam-gray-900, 6%);
$uam-surface-darker: lighten($uam-gray-900, 2%);

/* Tipografía general: mejorar legibilidad en base oscura */
body {
  color: #e9ecef;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* Enlaces */
a,
.link {
  color: var(--uam-primary);
}
a:hover,
a:focus {
  color: darken($uam-primary, 15%);
  text-decoration: underline;
}

/* Botones */
.btn-primary,
[type="submit"].btn.btn-primary {
  color: var(--uam-primary-contrast);
  background-color: var(--uam-primary);
  border-color: var(--uam-primary);
}
.btn-primary:hover,
.btn-primary:focus,
.btn-primary:active {
  color: var(--uam-primary-contrast);
  background-color: darken($uam-primary, 10%);
  border-color: darken($uam-primary, 12%);
}
.btn-secondary {
  color: var(--uam-secondary-contrast);
  background-color: var(--uam-secondary);
  border-color: var(--uam-secondary);
}
.btn-link { color: var(--uam-primary); }

/* Formularios y estados de foco accesibles */
input.form-control,
select.form-select,
textarea.form-control {
  border-color: #ced4da;
}
input.form-control:focus,
select.form-select:focus,
textarea.form-control:focus,
.form-control:focus,
.form-select:focus {
  border-color: var(--uam-focus);
  box-shadow: 0 0 0 .25rem rgba(173, 37, 168, .25); /* #AD25A8 con alpha */
}

/* Alertas */
.alert-info { border-left: .25rem solid var(--uam-primary); }
.alert-primary {
  background-color: mix($uam-primary, #fff, 15%);
  border-color: mix($uam-primary, #fff, 40%);
  color: var(--uam-gray-900);
}

/* Tablas */
table.table thead th {
  background: mix($uam-primary, #fff, 96%);
  color: var(--uam-gray-900);
}
table.table tbody tr:hover {
  /* Usar variable SCSS en mix() para evitar error de compilación (Sass no acepta var() dentro de mix). */
  background: mix($uam-gray-100, #fff, 50%);
}

/* Navbar / Header – morado UAM con texto blanco */
.navbar,
.primary-navigation {
  background-color: var(--uam-primary) !important;
}
.navbar .navbar-brand,
.navbar .navbar-brand a,
.navbar .navbar-nav .nav-link,
.primary-navigation .nav-link,
/* Forzar el título de sitio a blanco sobre fondo morado */
.navbar .site-name,
.navbar .sitename,
.navbar .navbar-brand .site-name,
.navbar .brand .site-name {
  color: var(--uam-white) !important;
}
.navbar.bg-white,
.navbar.bg-light,
.navbar.navbar-light {
  background-color: var(--uam-primary) !important;
}
.navbar .navbar-nav .nav-link:hover,
.navbar .navbar-nav .nav-link:focus {
  color: mix($uam-white, $uam-black, 15%) !important; /* Un blanco ligeramente atenuado para hover */
}
/* Toggler en móvil visible sobre fondo morado */
.navbar .navbar-toggler { color: #fff; border-color: rgba(255,255,255,.55); }
.navbar .navbar-toggler-icon { filter: invert(1) brightness(2); }

/* Logo institucional en navbar (cuando aplique) */
/* Logo institucional: aplicar también directamente al elemento para mayor compatibilidad */
.navbar .navbar-brand {
  position: relative;
  min-height: 32px;
  padding-left: 120px; /* deja espacio para el logo */
  background: url('[[pix:theme|uam-logo]]') no-repeat left 12px center / 112px 28px;
  background-color: transparent !important; /* evitar bloques blancos debajo del título */
}
.navbar .navbar-brand::before {
  content: '';
  display: inline-block;
  width: 112px; height: 28px;
  margin-right: .75rem;
  background: url('[[pix:theme|uam-logo]]') no-repeat center/contain;
}
.navbar .navbar-brand img { display: none; } /* ocultar logo por defecto para no duplicar */
.navbar .navbar-brand, .navbar .navbar-brand a { background-color: transparent !important; }

/* Footer */
#page-footer,
footer.footer {
  background: var(--uam-black);
  color: var(--uam-white);
}
#page-footer a { color: mix($uam-white, $uam-primary, 70%); }
#page-footer a:hover { color: var(--uam-white); }

/* Login */
/* Fondo de login: usar SIEMPRE la imagen institucional, sin color blanco de fondo */
html body.pagelayout-login,
html body.pagelayout-login #page,
body.pagelayout-login,
body.pagelayout-login #page {
  background: url('[[pix:theme|fondo-uam]]') no-repeat center center fixed !important;
  background-size: cover !important;
  background-color: transparent !important;
}
body.pagelayout-login #region-main .card,
body.pagelayout-login .login-container .card {
  border-radius: .75rem;
  border: none;
  box-shadow: 0 10px 30px rgba(0,0,0,.25);
}
/* Caja principal de login sin fondo blanco */
body.pagelayout-login .login-container {
  background-color: rgba(0,0,0,.35) !important;
  color: #fff;
  border: 1px solid rgba(255,255,255,.12);
}
body.pagelayout-login h1, body.pagelayout-login h2, body.pagelayout-login h3 {
  color: #fff;
}

/* Logo institucional dentro de la caja de login (aprovecha el contenedor de Boost) */
body.pagelayout-login .login-container .login-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 56px;
  margin-bottom: .75rem;
  background: url('[[pix:theme|uam-logo]]') no-repeat center/contain;
}
/* Si existe una imagen interna, mantenla oculta para no duplicar */
body.pagelayout-login .login-container .login-logo img {
  opacity: 0;
  width: 0; height: 0;
}

/* Dashboard, tarjetas y navegación lateral */
.block.card,
.card.dashboard-card,
.card.course-card {
  border: none;
  box-shadow: 0 6px 16px rgba(0,0,0,.08);
}
.block.card .card-header,
.card .card-header {
  background: transparent;
  color: #f1f3f5;
}

/* Índice del curso y navegación secundaria */
.secondary-navigation .nav-link.active,
.secondary-navigation .nav-link[aria-current="page"] {
  color: var(--uam-primary);
  border-color: var(--uam-primary);
}

/* Actividades: encabezado y botones de acción */
.activity-header,
.activity-navigation .btn-link {
  color: var(--uam-primary);
}

/* Estados hover/focus para elementos interactivos */
.list-group-item-action:hover,
.dropdown-item:hover {
  background: mix($uam-primary, #fff, 10%);
}
.list-group-item-action:focus,
.dropdown-item:focus {
  outline: 3px solid rgba(173, 37, 168, .35);
  outline-offset: 0;
}

/* Badges, etiquetas y chips */
.badge-primary, .badge.bg-primary {
  background: var(--uam-primary) !important;
}

/* Formularios: labels y ayudas */
label, .form-label { color: #f1f3f5; }
.form-text { color: #ced4da; }

/* Cursos: tarjetas en vista de cursos */
.coursebox, .course-card {
  border: 1px solid rgba(0,0,0,.06);
  border-radius: .5rem;
}
.coursebox .course-title a,
.course-card .coursename a { color: var(--uam-primary); }

/* Breadcrumbs */
.breadcrumb .breadcrumb-item a { color: var(--uam-primary); }
.breadcrumb .breadcrumb-item.active { color: #ced4da; }

/* Responsive */
@media (max-width: 991.98px) {
  .navbar .navbar-brand { padding-left: 104px; background-size: 96px 24px; }
  .navbar .navbar-brand::before { width: 96px; height: 24px; }
}
@media (max-width: 575.98px) {
  .navbar .navbar-brand { padding-left: 96px; background-size: 88px 22px; }
  .navbar .navbar-brand::before { width: 88px; height: 22px; }
}

/* Asegurar contraste mínimo AA en elementos clave */
.btn-primary { text-shadow: 0 1px 0 rgba(0,0,0,.2); }
.navbar .nav-link { text-shadow: none; }

/* Realces adicionales en todo el sitio */
/* Tabs y navegación secundaria */
.nav-tabs .nav-link.active,
.nav-tabs .nav-item.show .nav-link {
  color: var(--uam-primary);
  border-color: var(--uam-primary) var(--uam-primary) transparent;
}
.nav-tabs .nav-link:hover {
  border-color: mix($uam-primary, #fff, 60%);
}

/* Botones de acción en cabecera de curso */
.page-context-header .btn,
.page-header-headings + .btn,
.context-header-settings-menu .btn {
  border-radius: .4rem;
}

/* Cajones laterales (drawers) */
#nav-drawer {
  background: #0f0f10;
}
#nav-drawer .list-group .list-group-item {
  background: transparent;
  color: #e9ecef;
}
#nav-drawer .list-group .list-group-item:hover,
#nav-drawer .list-group .list-group-item.active {
  background: rgba(173, 37, 168, .15);
  color: #ffffff;
}

/* Tarjetas de curso y portada de curso */
.course-card .course-summaryitem,
.coursebox {
  transition: transform .15s ease, box-shadow .15s ease;
}
.course-card:hover,
.coursebox:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,.08); }

/* Encabezados de bloques con acento */
.block .card-header {
  border-left: 4px solid var(--uam-primary);
}

/* Actividades (módulos) */
.activity-item .activity-badges .badge { background: var(--uam-primary); }
.activity-item .activity-actions .btn-link { color: var(--uam-primary); }

/* Foro */
.forum-post .subject { color: var(--uam-primary); }
.forum-post .header .author a { color: var(--uam-gray-700); }

/* Cuestionarios */
.path-mod-quiz .qnbutton .thispageholder .qnbutton.qnbutton:hover { background: mix($uam-primary, #fff, 15%); }
.path-mod-quiz .qnbutton .qnbutton.flagged { border-color: var(--uam-primary); }

/* Notificaciones y mensajes */
.toast-info { border-left: .25rem solid var(--uam-primary); }
.message-app .conversations .conversation .name { color: var(--uam-primary); }

/* Formularios: switches y checkboxes */
.custom-control-input:checked ~ .custom-control-label::before {
  color: #fff;
  border-color: var(--uam-primary);
  background-color: var(--uam-primary);
}

/* Footer widgets / enlaces */
#page-footer .list-unstyled li a { text-decoration: none; }
  #page-footer .list-unstyled li a:hover { text-decoration: underline; }

/* ==========================================================
   Ampliación de diseño UAM Lerma para TODAS las páginas
   ========================================================== */

/* Fondo gris oscuro en todo el sitio (excepto login) */
html,
body,
body.pagelayout-standard,
body.pagelayout-course,
body.pagelayout-incourse,
body.pagelayout-frontpage,
body.pagelayout-mydashboard,
body.pagelayout-mycourses,
body.pagelayout-admin,
body.pagelayout-report,
body.pagelayout-secure,
body.pagelayout-popup,
body.pagelayout-embedded,
body.pagelayout-maintenance,
body.pagelayout-print,
body.pagelayout-redirect {
  background-color: #2b2f33 !important;
  background-image: none !important;
}
/* Asegurar transparencia de contenedores base para que se vea el fondo */
#page,
#page-wrapper,
#page-content,
.region-main,
#region-main,
.main-inner,
.drawercontent,
.drawer,
.container-fluid,
.container {
  background: transparent !important;
}
/* Mantener el cajón de navegación legible sobre el fondo */
#nav-drawer { background-color: rgba(0,0,0,.8) !important; }

/* Cabecera de página / contexto (curso, perfil, etc.) */
.page-context-header {
  background: linear-gradient(90deg, rgba(0,0,0,.55), rgba(0,0,0,.35));
  border-radius: .5rem;
}
.page-context-header .page-header-headings h1,
.page-context-header .page-header-headings h2,
.page-context-header .page-header-headings .page-header-headings-title {
  color: #fff;
}
.page-context-header .btn-link { color: mix(#fff, $uam-primary, 30%); }

/* Marca de agua del logo en cabeceras anchas */
.page-context-header::after {
  content: '';
  position: absolute;
  inset: auto 1rem 1rem auto;
  width: 120px; height: 28px;
  background: url('[[pix:theme|uam-logo]]') no-repeat center/contain;
  opacity: .35;
  pointer-events: none;
}

/* Frontpage y dashboard: tarjetas de cursos más vivas */
.path-site #page .coursebox, .path-my .coursebox,
.path-my .block_myoverview .card, .path-my .block .card {
  border: 1px solid rgba(0,0,0,.06);
  box-shadow: 0 8px 18px rgba(0,0,0,.06);
}
.path-site .coursebox .course-title a,
.path-my .block_myoverview .coursename a { color: var(--uam-primary); }

/* Gradebook (libro de calificaciones) */
.path-grade-report .gradereporttable thead th {
  background: $uam-surface-darker;
  color: #ffffff;
  border-bottom: 2px solid mix($uam-primary, #000, 60%);
}
.path-grade-report .gradereporttable tbody tr:hover td {
  background: rgba(255,255,255,.05);
}
.path-grade-report .gradereporttable .highlight, 
.path-grade-report-grader .gradeparent .highlight {
  outline: 2px solid rgba(173,37,168,.35);
  outline-offset: -2px;
}

/* Calendario */
.path-calendar .calendarmonth td.today .day-number {
  background: var(--uam-primary);
  color: #fff;
  border-radius: .25rem;
}
.path-calendar .eventlist .event .name a { color: var(--uam-primary); }
.path-calendar .calendar_event_course { background: mix($uam-primary, #fff, 90%); }

/* Mensajería */
.message-app .navbar, .message-app .header-container { background: var(--uam-primary); color: #fff; }
.message-app .conversations .conversation.active {
  background: rgba(173,37,168,.12);
}
.message-app .message.send .content {
  background: mix($uam-primary, #fff, 20%);
}

/* Perfil de usuario */
.path-user .userprofile .profile_tree h3 {
  border-left: 4px solid var(--uam-primary);
  padding-left: .5rem;
}
.path-user .profile_tree .contentnode a { color: var(--uam-primary); }

/* Administración: tablas y formularios */
.path-admin table.generaltable thead th {
  background: $uam-surface-darker;
  color: #fff;
}
.path-admin .mform .fitem .fitemtitle label { color: #f1f3f5; }
.path-admin .settingsform .form-submit .btn-primary { background: var(--uam-primary); border-color: var(--uam-primary); }

/* Paginación (refuerzo) */
.pagination .page-item.active .page-link {
  background: var(--uam-primary);
  border-color: var(--uam-primary);
  color: #fff;
}
.pagination .page-link:hover { color: var(--uam-primary); }

/* Barras de progreso */
.progress-bar { background-color: var(--uam-primary); }

/* Tags/etiquetas */
.tag, .badge.bg-info, .badge.bg-secondary { border-radius: .35rem; }
.tag a, .badge a { color: inherit; }

/* Actividades específicas (Assign, Quiz, Forum ya cubiertos en parte) */
.path-mod-assign .submissionstatustable th { background: $uam-surface-darker; color: #fff; }
.path-mod-assign .gradingtable tbody tr:hover td { background: rgba(255,255,255,.05); }
.path-mod-assign .submissionstatustable caption,
.path-mod-assign h2, .path-mod-assign h3 {
  color: var(--uam-primary);
}
.path-mod-forum .discussion .starter .subject a { color: var(--uam-primary); }
.path-mod-quiz .quiznavigation .qnbutton.thispage { border-color: var(--uam-primary); }

/* Navegación secundaria y course index (refuerzos) */
.secondary-navigation .nav-link:hover { color: darken($uam-primary, 8%); }
.courseindex .courseindex-item .courseindex-link:hover {
  background: rgba(173,37,168,.08);
}

/* Footer con separación superior más marcada */
#page-footer { border-top: 4px solid mix($uam-primary, #000, 30%); }

/* ==========================================================
   Mejora creativa adicional para reducir zonas en blanco
   y dar más carácter visual manteniendo AA
   ========================================================== */

/* Encabezados con subrayado/acento sutil en el contenido principal */
#region-main h1, #region-main h2, #region-main h3 {
  position: relative;
  padding-bottom: .25rem;
  background-image: linear-gradient(to right, rgba(173,37,168,.55), rgba(173,37,168,0));
  background-repeat: no-repeat;
  background-size: 40% 3px;
  background-position: left calc(100% + 2px);
}

/* Separadores con color institucional */
hr {
  border: 0;
  height: 2px;
  background: linear-gradient(to right, rgba(173,37,168,.45), rgba(173,37,168,0));
  opacity: 1;
}

/* Hero más alto en frontpage y dashboard usando la cabecera de contexto ya existente */
.path-site .page-context-header,
.path-my .page-context-header {
  min-height: 180px;
  padding: 2rem 1.25rem;
  display: flex;
  align-items: center;
}
.path-site .page-context-header .page-header-headings h1,
.path-my .page-context-header .page-header-headings h1 {
  font-weight: 700;
  letter-spacing: .2px;
}
.path-site .page-context-header .btn,
.path-my .page-context-header .btn {
  border-radius: .45rem;
}

/* Secciones del curso con panel suave y borde izquierdo UAM */
.course-content .section,
.format-tiles .tile,
.format-topics .section,
.format-weeks .section {
  background: rgba(173,37,168,.02);
  border-left: 4px solid var(--uam-primary);
  border-radius: .5rem;
  padding: .75rem 1rem;
  margin-bottom: .85rem;
}
.course-content .section .sectionname {
  color: #f1f3f5;
}
.course-content .section .summary,
.course-content .section .content {
  color: #e9ecef;
}

/* Actividades: resaltar hover del renglón y acciones */
.activity-item { transition: background-color .15s ease, box-shadow .15s ease; }
.activity-item:hover {
  background: rgba(255,255,255,.05);
  box-shadow: 0 6px 14px rgba(0,0,0,.2);
}
.activity-item .actions .btn-link,
.activity-item .activity-badges .badge {
  color: var(--uam-primary);
}

/* Login: overlay sutil para mejorar legibilidad de la tarjeta (sin blanco) */
body.pagelayout-login #page::before {
  content: '';
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.25);
}

/* Botones outline con colores UAM */
.btn-outline-primary {
  color: var(--uam-primary);
  border-color: var(--uam-primary);
}
.btn-outline-primary:hover { background: var(--uam-primary); color: #fff; }

/* Superficies (modo oscuro): evitar blanco liso, usar grises oscuros accesibles */

.card,
.block.card,
.dropdown-menu,
.modal-content,
.popover,
.toast {
  background-color: $uam-surface-dark;
  color: #f1f3f5;
}
.card .card-header { background-color: $uam-surface-darker; color: #fff; }
.bg-white { background-color: $uam-surface-dark !important; }
.bg-light, .bg-body, .bg-body-tertiary { background-color: $uam-surface-dark !important; }

/* Diálogos clásicos de Moodle */
.moodle-dialogue,
.moodle-dialogue .moodle-dialogue-wrap { background-color: $uam-surface-dark; color: #f1f3f5; }
.moodle-dialogue .moodle-dialogue-hd { background-color: $uam-surface-darker; color: #fff; border-bottom: 1px solid rgba(255,255,255,.15); }
.moodle-dialogue .moodle-dialogue-bd { background-color: $uam-surface-dark; color: #f1f3f5; }

/* Drawer de mensajería */
.message-app .drawer,
.message-app .drawer .drawercontent { background-color: $uam-surface-dark; }
.message-app .drawer .list-group .list-group-item { background-color: transparent; color: #e9ecef; }

/* ==========================================================
   Menús desplegables y selects: opciones en fondo BLANCO
   (mejor legibilidad en menús de Perfil, Idioma, Acción, etc.)
   ========================================================== */
/* Menús genéricos */
.dropdown-menu,
.menu .dropdown-menu,
.usermenu .dropdown-menu,
.moodle-actionmenu .menu .dropdown-menu,
.login-languagemenu .dropdown-menu {
  background-color: #ffffff !important;
  color: #212529 !important;
  border-color: rgba(0,0,0,.15) !important;
}
.dropdown-item {
  color: #212529 !important;
}
.dropdown-item:hover,
.dropdown-item:focus {
  background: mix($uam-primary, #fff, 92%) !important;
  color: #212529 !important;
}
.dropdown-item.active,
.dropdown-item:active {
  background: mix($uam-primary, #fff, 85%) !important;
  color: #212529 !important;
}

/* Selects nativos: intentar forzar opciones en blanco (según soporte del navegador) */
select option,
.form-select option {
  background-color: #ffffff !important;
  color: #212529 !important;
}
/* Mantener control legible en oscuro, pero con menú de opciones blanco cuando se despliega */
.form-select,
select.form-select,
select.form-control {
  color: #f1f3f5;
}

/* Campos de formulario en modo oscuro */
input.form-control,
textarea.form-control,
select.form-select,
.form-control,
.form-select {
  background-color: $uam-surface-darker;
  color: #f1f3f5;
  border-color: rgba(255,255,255,.15);
}
::placeholder { color: rgba(255,255,255,.6) !important; }

/* Tablas en modo oscuro */
table.table thead th { background-color: $uam-surface-darker; color: #fff; }
.table-striped > tbody > tr:nth-of-type(odd) > * {
  --bs-table-accent-bg: rgba(255,255,255,.03);
  color: #e9ecef;
}
.table tbody tr:hover > * { background-color: rgba(255,255,255,.05); }

/* Asegurar texto blanco sobre superficies rosas (primario) */
.bg-primary, .btn-primary, .badge.bg-primary, .nav-pills .nav-link.active, .nav-pills .show > .nav-link {
  color: #fff !important;
}

/* Pestañas en modo oscuro */
.nav-tabs .nav-link {
  color: #ced4da;
  background-color: transparent;
  border-color: rgba(255,255,255,.15);
}
.nav-tabs .nav-link.active,
.nav-tabs .nav-item.show .nav-link {
  color: #fff;
  background-color: $uam-surface-darker;
  border-color: var(--uam-primary) var(--uam-primary) transparent;
}
.nav-tabs .nav-link:hover { color: #fff; border-color: rgba(255,255,255,.35); }

/* Estados vacíos comunes como tarjetas ilustradas */
.empty-placeholder, .noitems, .norecords, .no-content {
  border: 2px dashed rgba(255,255,255,.2);
  background: rgba(255,255,255,.05);
  border-radius: .75rem;
  padding: 1rem;
}
.empty-placeholder::before, .noitems::before, .norecords::before, .no-content::before {
  content: '';
  display: block;
  width: 120px; height: 28px;
  margin: .25rem auto .75rem;
  background: url('[[pix:theme|uam-logo]]') no-repeat center/contain;
  opacity: .55;
}

/* Píldoras de estado y progreso más suaves */
.badge.rounded-pill { padding: .45rem .65rem; }
.progress { background: rgba(255,255,255,.08); }

/* Ajustes responsive adicionales */
@media (max-width: 767.98px) {
  .path-site .page-context-header, .path-my .page-context-header { min-height: 140px; padding: 1.25rem 1rem; }
  #region-main h1, #region-main h2, #region-main h3 { background-size: 60% 3px; }
}

SCSS;

    return $scss;
}

/**
 * SCSS que se inyecta ANTES de compilar Bootstrap/Boost.
 * Útil para sobreescribir variables de Bootstrap y así recolorear componentes nativos.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_uam_get_pre_scss($theme): string {
    $prescss = <<<'SCSS'
// Variables Bootstrap/Boost sobreescritas por UAM Lerma
$primary: #AD25A8;
$secondary: #495057;
$body-color: #e9ecef; // Texto claro para base oscura
$body-bg: transparent; // Evita fondo blanco por defecto en todo el sitio
$link-color: $primary;

// Colores adicionales de estado (mantener accesibilidad)
$success: #198754;
$info: #0dcaf0;
$warning: #ffc107;
$danger: #dc3545;

// Navbar oscuro institucional
$navbar-dark-color: #ffffff;
$navbar-dark-hover-color: mix(#ffffff, $primary, 75%);
$navbar-dark-active-color: $navbar-dark-hover-color;
$navbar-dark-brand-color: #ffffff;
$navbar-dark-brand-hover-color: $navbar-dark-hover-color;
$navbar-dark-bg: $primary;

// En caso de que Boost use la variante "light", forzar también el fondo morado y texto blanco
$navbar-light-bg: $primary;
$navbar-light-color: #ffffff;
$navbar-light-hover-color: mix(#ffffff, $primary, 15%);
$navbar-light-active-color: #ffffff;
$navbar-light-brand-color: #ffffff;
$navbar-light-brand-hover-color: #ffffff;

// Botones
$btn-focus-width: .25rem;
$btn-focus-box-shadow: 0 0 0 .25rem rgba(173, 37, 168, .25);

// Paginación
$pagination-color: $body-color;
$pagination-hover-color: $primary;
$pagination-active-color: #fff;
$pagination-active-bg: $primary;
$pagination-active-border-color: $primary;

// Progress bar y badges
$progress-bar-bg: $primary;
$badge-color: #fff;
$badge-bg: $primary;
$badge-border-radius: .35rem;

// Tabs
$nav-tabs-link-active-color: $primary;
$nav-tabs-link-hover-border-color: mix($primary, #fff, 60%) mix($primary, #fff, 60%) transparent;

// Breadcrumb
$breadcrumb-divider-color: $secondary;
$breadcrumb-active-color: $secondary;

// Login: eliminar gradiente por defecto para que se vea la imagen institucional
$loginbackground-gradient-from: transparent;
$loginbackground-gradient-to: transparent;

// Modo oscuro por defecto para superficies nativas de Bootstrap
$card-bg: lighten(#212529, 6%);
$dropdown-bg: lighten(#212529, 6%);
$modal-content-bg: lighten(#212529, 6%);
$popover-bg: lighten(#212529, 6%);
$toast-background-color: lighten(#212529, 6%);
$input-bg: lighten(#212529, 2%);
$form-select-bg: lighten(#212529, 2%);
$table-bg: transparent;
$table-striped-bg: rgba(255, 255, 255, .03);
$table-hover-bg: rgba(255, 255, 255, .05);
SCSS;

    return $prescss;
}
