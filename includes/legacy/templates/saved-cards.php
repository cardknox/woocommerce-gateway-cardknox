<?php
/*
Copyright © 2018 Cardknox Development Inc. All rights reserved.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
?>
<h2 id="saved-cards" style="margin-top:40px;"><?php _e( 'Saved cards', 'woocommerce-gateway-cardknox' ); ?></h2>
<table class="shop_table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Card', 'woocommerce-gateway-cardknox' ); ?></th>
			<th><?php esc_html_e( 'Expires', 'woocommerce-gateway-cardknox' ); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $cards as $card ) :
			if ( 'card' !== $card->object ) {
				continue;
			}

			$is_default_card = $card->id === $default_card ? true : false;
		?>
		<tr>
            <td><?php printf( __( '%s card ending in %s', 'woocommerce-gateway-cardknox' ), $card->brand, $card->last4 ); ?>
            	<?php if ( $is_default_card ) echo '<br />' . __( '(Default)', 'woocommerce-gateway-cardknox' ); ?>
            </td>
            <td><?php printf( __( 'Expires %s/%s', 'woocommerce-gateway-cardknox' ), $card->exp_month, $card->exp_year ); ?></td>
			<td>
                <form action="" method="POST">
                    <?php wp_nonce_field ( 'cardknox_del_card' ); ?>
                    <input type="hidden" name="cardknox_delete_card" value="<?php echo esc_attr( $card->id ); ?>">
                    <input type="submit" class="button" value="<?php esc_attr_e( 'Delete card', 'woocommerce-gateway-cardknox' ); ?>">
                </form>

                <?php if ( ! $is_default_card ) { ?>
	                <form action="" method="POST" style="margin-top:10px;">
	                    <?php wp_nonce_field ( 'cardknox_default_card' ); ?>
	                    <input type="hidden" name="cardknox_default_card" value="<?php echo esc_attr( $card->id ); ?>">
	                    <input type="submit" class="button" value="<?php esc_attr_e( 'Make Default', 'woocommerce-gateway-cardknox' ); ?>">
	                </form>
                <?php } ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
