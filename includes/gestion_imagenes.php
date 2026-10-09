<?php
/**
 * Galería editable de un producto. Requiere $productoId y $urlFormularioImagenes.
 */
$imagenesProducto = obtenerTodos('SELECT id, ruta FROM producto_imagenes WHERE producto_id = ? ORDER BY orden, id', [$productoId]);
?>
<h5>Imágenes del producto (<?= count($imagenesProducto) ?>)</h5>
<?php if (!$imagenesProducto): ?>
    <p class="text-muted">El producto aún no tiene imágenes.</p>
<?php endif; ?>
<div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-2 mb-3">
    <?php foreach ($imagenesProducto as $indice => $imagenProducto): ?>
        <div class="col">
            <div class="card h-100">
                <img class="card-img-top imagen-producto" src="<?= e(rutaImagen($imagenProducto['ruta'])) ?>" alt="Imagen <?= $indice + 1 ?>">
                <div class="card-body p-2 text-center">
                    <?php if ($indice === 0): ?>
                        <span class="badge text-bg-success mb-1">Principal</span>
                    <?php else: ?>
                        <form method="post" action="<?= e($urlFormularioImagenes) ?>" data-confirmar="¿Desea usar esta imagen como principal?">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="accion" value="imagen_principal">
                            <input type="hidden" name="imagen_id" value="<?= (int) $imagenProducto['id'] ?>">
                            <button type="submit" class="btn btn-link btn-sm p-0">Hacer principal</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e($urlFormularioImagenes) ?>" data-confirmar="¿Desea eliminar esta imagen?">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="accion" value="eliminar_imagen">
                        <input type="hidden" name="imagen_id" value="<?= (int) $imagenProducto['id'] ?>">
                        <button type="submit" class="btn btn-link btn-sm text-danger p-0">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<form method="post" action="<?= e($urlFormularioImagenes) ?>" enctype="multipart/form-data" data-validar data-confirmar="¿Desea subir las imágenes seleccionadas?">
    <?= campoCsrf() ?>
    <input type="hidden" name="accion" value="subir_imagenes">
    <div class="mb-3 campo">
        <label class="form-label" for="imagenesNuevas">Agregar imágenes (puede seleccionar varias; JPG, PNG, WEBP o GIF de hasta 5 MB)</label>
        <input class="form-control" type="file" id="imagenesNuevas" name="imagenes[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required data-regla="requerido|imagenes" data-vista-previa="vistaPreviaNuevas">
        <div class="vista-previa" id="vistaPreviaNuevas"></div>
    </div>
    <button type="submit" class="btn btn-outline-primary">Subir imágenes</button>
</form>
