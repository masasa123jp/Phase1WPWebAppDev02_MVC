<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/MagazineModel.php';

/**
 * MagazineController fetches magazine issues and builds the data structure
 * used by magazine.js to render each issue.  It is intended to be called
 * from functions.php on the magazine page.
 */
class MagazineController extends BaseController {
    /**
     * Localises magazine data into a script handle.  The resulting
     * JavaScript variable `RORO_MAG_DATA` is an array where each element
     * contains an `id`, `title` and `pages` (array of objects with `html`).
     *
     * @param string $script_handle The handle of the magazine script
     */
    public function enqueue_magazine_data( $script_handle = 'roro-magazine' ) {
        $model  = new MagazineModel();
        $issues = $model->get_all();
        $data   = [];
        foreach ( $issues as $issue ) {
            $pages = [];
            // Convert cover image from blob to data URI if present
            $coverImage = '';
            if ( ! empty( $issue['cover_image'] ) && ! empty( $issue['cover_mime'] ) ) {
                $coverImage = $this->to_data_uri( $issue['cover_image'], $issue['cover_mime'] );
            } elseif ( ! empty( $issue['cover_image'] ) ) {
                // In case cover_image stores a URL instead of blob
                $coverImage = esc_url( $issue['cover_image'] );
            }
            // Cover page: image + title + theme
            $cover_html = '<div style="display:flex;flex-direction:column;height:100%;">';
            if ( $coverImage ) {
                $cover_html .= '<img src="' . esc_attr( $coverImage ) . '" alt="cover" style="width:100%;height:65%;object-fit:cover;border-radius:8px;">';
            }
            $cover_html .= '<div style="padding:0.4rem;text-align:center;">';
            $cover_html .= '<h2 style="margin:0;color:#1F497D;">' . esc_html( $issue['title'] ) . '</h2>';
            if ( ! empty( $issue['theme_title'] ) ) {
                $cover_html .= '<h3 style="margin:0.2rem 0;color:#e67a8a;font-size:1.3rem;">' . esc_html( $issue['theme_title'] ) . '</h3>';
            }
            if ( ! empty( $issue['theme_desc'] ) ) {
                $cover_html .= '<p style="font-size:1.0rem;">' . esc_html( $issue['theme_desc'] ) . '</p>';
            }
            $cover_html .= '</div></div>';
            $pages[] = [ 'html' => $cover_html ];
            // Build pages from articles
            foreach ( $issue['articles'] as $article ) {
                $html = '';
                if ( ! empty( $article['title'] ) ) {
                    $html .= '<h3 style="color:#1F497D;">' . esc_html( $article['title'] ) . '</h3>';
                }
                // Convert article image blob to data URI if present
                $articleImg = '';
                if ( ! empty( $article['image'] ) && ! empty( $article['image_mime'] ) ) {
                    $articleImg = $this->to_data_uri( $article['image'], $article['image_mime'] );
                } elseif ( ! empty( $article['image_url'] ) ) {
                    $articleImg = esc_url( $article['image_url'] );
                }
                if ( $articleImg ) {
                    $html .= '<img src="' . esc_attr( $articleImg ) . '" alt="' . esc_attr( $article['title'] ) . '" style="width:100%;max-height:250px;object-fit:cover;border-radius:8px;margin-bottom:0.5rem;">';
                }
                if ( ! empty( $article['summary'] ) ) {
                    $html .= '<p style="font-size:0.9rem;">' . esc_html( $article['summary'] ) . '</p>';
                }
                $pages[] = [ 'html' => $html ];
            }
            // Closing page
            $closing = '<div style="background:#F9E9F3;display:flex;align-items:center;justify-content:center;height:100%;padding:1rem;">';
            $closing .= '<div style="writing-mode:vertical-rl; transform: rotate(180deg); font-size:1.4rem; color:#1F497D; text-align:center;">';
            $closing .= 'PROJECT RORO<br>' . esc_html( $issue['title'] ) . '</div></div>';
            $pages[] = [ 'html' => $closing ];
            $data[] = [
                'id'         => $issue['issue_code'],
                'title'      => $issue['title'],
                'theme_desc' => isset( $issue['theme_desc'] ) ? $issue['theme_desc'] : '',
                'cover_image'=> $coverImage,
                'pages'      => $pages,
            ];
        }
        wp_localize_script( $script_handle, 'RORO_MAG_DATA', $data );
    }

    /**
     * Convert binary blob and mime type into a data URI.
     * If blob is empty, returns empty string.
     *
     * @param string $blob The binary blob data
     * @param string $mime The MIME type
     * @return string Data URI or empty
     */
    private function to_data_uri( $blob, $mime ) {
        if ( empty( $blob ) ) {
            return '';
        }
        $base64 = base64_encode( $blob );
        return 'data:' . $mime . ';base64,' . $base64;
    }
}