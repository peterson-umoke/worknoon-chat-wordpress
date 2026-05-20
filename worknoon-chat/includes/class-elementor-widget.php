<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('elementor/widgets/register', function ($widgets_manager) {
    require_once WORKNOON_CHAT_PLUGIN_DIR . 'includes/class-elementor-widget.php';
    $widgets_manager->register(new Worknoon_Elementor_Chat_Widget());
});

add_action('elementor/elements/categories_registered', function ($elements_manager) {
    $elements_manager->add_category('worknoon-chat', [
        'title' => 'Worknoon Chat',
        'icon' => 'fa fa-comments',
    ]);
});

class Worknoon_Elementor_Chat_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'worknoon_chat_widget';
    }

    public function get_title() {
        return 'Worknoon Chat';
    }

    public function get_icon() {
        return 'eicon-chat';
    }

    public function get_categories() {
        return ['worknoon-chat'];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            ['label' => 'Chat Settings', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]
        );

        $this->add_control(
            'label',
            [
                'label' => 'Button Label',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Chat with us',
            ]
        );

        $this->add_control(
            'position',
            [
                'label' => 'Position',
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'bottom-right' => 'Bottom Right',
                    'bottom-left' => 'Bottom Left',
                ],
                'default' => 'bottom-right',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        echo do_shortcode('[worknoon_chat label="' . esc_attr($settings['label']) . '" position="' . esc_attr($settings['position']) . '"]');
    }
}
