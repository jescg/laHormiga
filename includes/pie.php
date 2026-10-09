<?php
/**
 * Pie de página común (Bootstrap 5).
 */
$plantilla = $plantilla ?? 'tienda';
?>
</main>
<?php if ($plantilla === 'panel'): ?>
    </div>
</div>
<?php endif; ?>

<?php if ($plantilla === 'tienda'): ?>
<footer class="bg-dark text-light pt-5 pb-3 mt-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <h5>La Hormiga</h5>
                <p class="text-secondary small">Tienda en línea de venta general de productos con envío a todo México.</p>
            </div>
            <div class="col-6 col-md-2">
                <h6>Tienda</h6>
                <ul class="list-unstyled small">
                    <li><a class="link-light link-underline-opacity-0" href="<?= e(url('tienda/catalogo.php')) ?>">Catálogo</a></li>
                    <li><a class="link-light link-underline-opacity-0" href="<?= e(url('tienda/carrito.php')) ?>">Carrito</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-2">
                <h6>Mi cuenta</h6>
                <ul class="list-unstyled small">
                    <li><a class="link-light link-underline-opacity-0" href="<?= e(url('tienda/cuenta.php')) ?>">Mi cuenta</a></li>
                    <li><a class="link-light link-underline-opacity-0" href="<?= e(url('tienda/pedidos.php')) ?>">Mis pedidos</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6>Contacto</h6>
                <p class="small text-secondary mb-1"><i class="bi bi-envelope"></i> contacto@lahormiga.mx</p>
                <p class="small text-secondary"><i class="bi bi-telephone"></i> 55 5729 6000</p>
            </div>
        </div>
        <hr class="border-secondary">
        <p class="small text-secondary text-center mb-0">&copy; <?= date('Y') ?> La Hormiga. Proyecto académico de Ingeniería Web, UPIITA-IPN. Los pagos son simulados.</p>
    </div>
</footer>
<?php endif; ?>

<div class="modal fade" id="modalConfirmacion" tabindex="-1" aria-labelledby="tituloModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloModal">Confirmar operación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body"><p id="textoModal" class="mb-0"></p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="botonAceptarModal">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/validaciones.js')) ?>"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
