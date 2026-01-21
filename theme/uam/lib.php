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

/* Tipografía general: mejorar legibilidad */
body {
  color: var(--uam-gray-900);
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
  background: var(--uam-gray-100);
  color: var(--uam-gray-900);
}
table.table tbody tr:hover {
  background: mix(var(--uam-gray-100), #fff, 50%);
}

/* Navbar / Header */
.navbar,
.primary-navigation {
  background-color: var(--uam-black) !important;
}
.navbar .navbar-brand,
.navbar .navbar-brand a,
.navbar .navbar-nav .nav-link,
.primary-navigation .nav-link {
  color: var(--uam-white) !important;
}
.navbar .navbar-nav .nav-link:hover,
.navbar .navbar-nav .nav-link:focus {
  color: mix($uam-white, $uam-primary, 75%) !important;
}

/* Logo institucional en navbar (cuando aplique) */
.navbar .navbar-brand::before {
  content: '';
  display: inline-block;
  width: 112px; height: 28px;
  margin-right: .75rem;
  background: url('[[pix:theme|uam-logo]]') no-repeat center/contain;
}

/* Footer */
#page-footer,
footer.footer {
  background: var(--uam-black);
  color: var(--uam-white);
}
#page-footer a { color: mix($uam-white, $uam-primary, 70%); }
#page-footer a:hover { color: var(--uam-white); }

/* Login */
body.pagelayout-login #page {
  background: var(--uam-gray-100) url('[[pix:theme|fondo-uam]]') no-repeat center center fixed;
  background-size: cover;
}
body.pagelayout-login #region-main .card,
body.pagelayout-login .login-container .card {
  border-radius: .75rem;
  border: none;
  box-shadow: 0 10px 30px rgba(0,0,0,.25);
}
body.pagelayout-login h1, body.pagelayout-login h2, body.pagelayout-login h3 {
  color: var(--uam-black);
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
  background: var(--uam-gray-100);
  color: var(--uam-gray-900);
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
label, .form-label { color: var(--uam-gray-900); }
.form-text { color: var(--uam-gray-700); }

/* Cursos: tarjetas en vista de cursos */
.coursebox, .course-card {
  border: 1px solid rgba(0,0,0,.06);
  border-radius: .5rem;
}
.coursebox .course-title a,
.course-card .coursename a { color: var(--uam-primary); }

/* Breadcrumbs */
.breadcrumb .breadcrumb-item a { color: var(--uam-primary); }
.breadcrumb .breadcrumb-item.active { color: var(--uam-gray-700); }

/* Responsive */
@media (max-width: 991.98px) {
  .navbar .navbar-brand::before { width: 96px; height: 24px; }
}
@media (max-width: 575.98px) {
  .navbar .navbar-brand::before { width: 88px; height: 22px; }
}

/* Asegurar contraste mínimo AA en elementos clave */
.btn-primary { text-shadow: 0 1px 0 rgba(0,0,0,.2); }
.navbar .nav-link { text-shadow: none; }

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
$body-color: #212529;
$link-color: $primary;

// Navbar oscuro institucional
$navbar-dark-color: #ffffff;
$navbar-dark-hover-color: mix(#ffffff, $primary, 75%);
$navbar-dark-active-color: $navbar-dark-hover-color;
$navbar-dark-brand-color: #ffffff;
$navbar-dark-brand-hover-color: $navbar-dark-hover-color;
$navbar-dark-bg: #000000;

// Botones
$btn-focus-width: .25rem;
$btn-focus-box-shadow: 0 0 0 .25rem rgba(173, 37, 168, .25);
SCSS;

    return $prescss;
}
