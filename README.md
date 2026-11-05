# Website

## Plugin: Patentado – Clase Muestra

Plugin de WordPress (`patentado-muestra/`) para enviar a pastores, coaches y líderes una muestra de la clase **Prioridad**:

- Ingreso con **email + contraseña generada** automáticamente (se puede enviar por email).
- **Panel de alumno** con el currículo completo (las demás clases aparecen bloqueadas).
- **Video completo** sin restricciones de adelantar/retroceder (YouTube, Vimeo o MP4), midiendo cuánto vieron realmente.
- **PDF** para ver en la página y descargar.
- **Quiz** calificado en el servidor.
- Al aprobar: **felicitación** como si terminaran todo el currículo + **certificado** con marca de agua **SAMPLE** (descarga PDF / imprimir).
- En wp-admin: menú **Alumnos Muestra** con la lista, progreso (video %, PDF, quiz, estado), reenviar acceso, nueva contraseña, reiniciar progreso, eliminar y exportar CSV.

### Instalación

1. Descarga `dist/patentado-muestra.zip`.
2. WordPress → **Plugins → Añadir nuevo → Subir plugin** → elegir el zip → **Activar**.
3. Se crea la página **Clase Muestra** (`/clase-muestra/`) con el shortcode `[patentado_muestra]`.
4. **Alumnos Muestra → Ajustes de la clase**: pega el video, sube el PDF, escribe el quiz, logo y colores.
5. **Alumnos Muestra → Alumnos y progreso**: agrega a cada invitado con su email.

Formato del quiz:

```
¿Pregunta?
* respuesta correcta
- respuesta incorrecta

¿Siguiente pregunta?
...
```

Los alumnos muestra tienen el rol `alumno_muestra`: no ven el escritorio de WordPress ni otras clases.
