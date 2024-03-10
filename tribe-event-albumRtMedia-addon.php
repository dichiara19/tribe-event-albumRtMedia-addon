<?php
/**
 * Plugin Name: RTMedia to The Event Calendar
 * Description: Un plugin per aggiungere il supporto agli album di RTMedia negli eventi creati con The Events Calendar.
 * Version: 1.0
 * Author: Giuseppe Di Chiara
 * Author URI: https://www.giuseppedichiara.it
 */

/* Add album RTMedia search on Event post (tribe-events) */

add_action('wp_ajax_cerca_album', 'cerca_album_ajax_handler');
add_action('wp_ajax_nopriv_cerca_album', 'cerca_album_ajax_handler');

function cerca_album_ajax_handler() {
    global $wpdb;
    $search_term = isset($_POST['termine']) ? '%' . $wpdb->esc_like(sanitize_text_field($_POST['termine'])) . '%' : '';

    $query = $wpdb->prepare(
        "SELECT id, media_title, media_id FROM {$wpdb->prefix}rt_rtm_media WHERE media_type = 'album' AND context = 'group' AND media_title LIKE %s",
        $search_term
    );

    $albums = $wpdb->get_results($query);

    // Prepara i dati per l'output.
    $results = array();
    foreach ($albums as $album) {
        $results[] = array(
            'id' => $album->id,
            'title' => $album->media_title,
            'url' => get_permalink($album->media_id)
        );
    }

    wp_send_json_success($results);
}

add_action('add_meta_boxes', 'aggiungi_metabox_album_evento');

function aggiungi_metabox_album_evento() {
    add_meta_box(
        'id-metabox-album-evento',
        'Cerca Album Evento',
        'html_metabox_album_evento', // Callback
        'tribe_events', // Post type by The Events Calendar
        'normal', 
        'high'
    );
}

function html_metabox_album_evento($post) {
    wp_nonce_field('nonce_controllo_sicurezza_album_evento', 'nonce_album_evento');
    echo '<input id="input-cerca-album-evento" type="text" name="cerca_album_evento" placeholder="Cerca Album..." />';
    echo '<div id="risultati-album-evento"></div>';

    // retrieves both the URL and title of the selected album from the post's metadata
    $url_album_selezionato = get_post_meta($post->ID, 'url_album_evento', true);
    $title_album_selezionato = get_post_meta($post->ID, 'title_album_evento', true);

    echo '<div id="album-selezionato-container">';
    if (!empty($url_album_selezionato) && !empty($title_album_selezionato)) {
        echo '<p><strong>Album selezionato:</strong> <a href="' . esc_url($url_album_selezionato) . '" target="_blank">' . esc_html($title_album_selezionato) . '</a></p>';
    } else {
        echo '<p><em>Nessun album selezionato</em></p>';
    }
    echo '</div>';

    // hidden field to store the URL of the selected album
    echo '<input id="url-album-selezionato" type="hidden" name="url_album_evento" value="' . esc_attr($url_album_selezionato) . '"/>';
    // hidden field also for the selected album title
    echo '<input id="title-album-selezionato" type="hidden" name="title_album_evento" value="' . esc_attr($title_album_selezionato) . '"/>';
}


add_action('admin_footer', 'aggiungi_script_metabox_album_evento');

function aggiungi_script_metabox_album_evento() {
    $screen = get_current_screen();
    if ($screen->id !== 'tribe_events') return; 

    ?>
    <script type="text/javascript">
jQuery(document).ready(function($) {
    $('#input-cerca-album-evento').on('keyup', function() {
        var searchTerm = $(this).val();
        if(searchTerm.length < 3) return; 
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cerca_album',
                termine: searchTerm
            },
            success: function(response) {
                $('#risultati-album-evento').empty();
                if(response.success && response.data.length > 0) {
                    $.each(response.data, function(i, album) {
                      // replace your-url.com
                        var albumUrl = `http://your-url.com/bp-groups/event-gallery/media/` + album.id;
                        $('#risultati-album-evento').append(`<div class="album-selezionabile" data-url="${albumUrl}" data-title="${album.title}">${album.title}</div>`);
                    });
                    $('.album-selezionabile').on('click', function() {
                        var urlSelezionato = $(this).data('url');
                        var titleSelezionato = $(this).data('title');
                        $('#url-album-selezionato').val(urlSelezionato);
                        $('#title-album-selezionato').val(titleSelezionato); // hidden field
                        $('#album-selezionato-container').html('<p><strong>Album selezionato:</strong> <a href="' + urlSelezionato + '" target="_blank">' + titleSelezionato + '</a></p>');
                    });
                } else {
                    $('#risultati-album-evento').append('<div>Nessun album trovato.</div>');
                }
            }
        });
    });
});
</script>
    <?php
}

add_action('save_post_tribe_events', 'salva_url_album_evento', 10, 2);

function salva_url_album_evento($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['nonce_album_evento']) || !wp_verify_nonce($_POST['nonce_album_evento'], 'nonce_controllo_sicurezza_album_evento')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['url_album_evento'])) {
        update_post_meta($post_id, 'url_album_evento', sanitize_text_field($_POST['url_album_evento']));
    }
    if (isset($_POST['title_album_evento'])) { // Salviamo anche il titolo dell'album
        update_post_meta($post_id, 'title_album_evento', sanitize_text_field($_POST['title_album_evento']));
    }
}


add_action('tribe_events_single_event_after_the_meta', 'my_custom_button_after_meta');

function my_custom_button_after_meta() {
    $url_album_evento = get_post_meta(get_the_ID(), 'url_album_evento', true);
    if (!empty($url_album_evento)) {
        echo '<div class="my-custom-button-container">';
        echo '<a href="' . esc_url($url_album_evento) . '" class="btn btn-primary" target="_blank">Accedi alla Gallery</a>';
        echo '</div>';
    }
}

function carica_stile_metabox_album_evento() {
    global $pagenow, $typenow;
    if ( $pagenow == 'post.php' && $typenow == 'tribe_events' ) {
        // registers a dummy style (does not actually load a file)
        wp_register_style( 'event-album-metabox-css', false );
        wp_enqueue_style( 'event-album-metabox-css' );
        
        // adds inline CSS styling
        wp_add_inline_style( 'event-album-metabox-css', '

            /* Stile per il metabox */
            #id-metabox-album-evento {
                background-color: #f9f9f9;
                padding: 15px;
                border: 1px solid #ddd;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }

            /* Stile per input di ricerca */
            #input-cerca-album-evento {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }

            /* Stile per i risultati di ricerca */
            #risultati-album-evento {
                margin-top: 10px;
                max-height: 200px;
                overflow-y: auto;
                border: 1px solid #ddd;
                border-radius: 4px;
                background-color: #fff;
            }

            /* Stile per gli elementi selezionabili */
            .album-selezionabile {
                padding: 8px;
                cursor: pointer;
                transition: background-color 0.3s;
            }

            .album-selezionabile:hover {
                background-color: #f5f5f5;
            }

            /* Stile per il contenitore dellalbum selezionato */
            #album-selezionato-container {
                margin-top: 15px;
                padding: 15px;
                background-color: #e9ffd9;
                border: 1px solid #b7daa8;
                border-radius: 4px;
            }

            #album-selezionato-container p {
                margin: 0;
                font-size: 14px;
                line-height: 1.4;
            }

            #album-selezionato-container a {
                text-decoration: none;
                font-weight: bold;
                color: #2a6496;
            }

            #album-selezionato-container a:hover {
                text-decoration: underline;
            }
        ' );
    }
}
add_action( 'admin_enqueue_scripts', 'carica_stile_metabox_album_evento' );
