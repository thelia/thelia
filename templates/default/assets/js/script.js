/* JQUERY PREVENT CONFLICT */
(function($) {


/*	------------------------------------------------------------------
	onLoad Function -------------------------------------------------- */
	$(document).ready(function(){


		// Main Navigation Hover
		$('.nav-main')
		.on('click.subnav', '[data-toggle=dropdown]', function(event){
			if($(this).parent().hasClass('open') && $(this).is(event.target))
				return false;
		})
		.on('mouseenter.subnav', '.dropdown', function(event){
			if($(this).hasClass('open'))
				return;

			$(this).addClass('open');
		})
		.on('mouseleave.subnav', '.dropdown', function(){
			if(!$(this).hasClass('open'))
				return;
			$(this).removeClass('open');
		});

		// Tooltip
		$('body').tooltip({
			selector: '[data-toggle=tooltip]'
		});

		// Toolbar
		var $category_products = $('#category-products');
		if($category_products.size() > 0){
			var $parent = $category_products.parent();


			$parent.on('click.view-mode', '[data-toggle=view]', function(){
				if( ($(this).hasClass('btn-grid') && $parent.hasClass('grid')) || ($(this).hasClass('btn-list') && $parent.hasClass('list')))
					return;

				$parent.toggleClass('grid').toggleClass('list');

				return false;
			});
		}

		// Login
/*		var $form_login = $('#form-login');
		if($form_login.size() > 0) {
			$form_login.on('change.account', ':radio', function(){
				if($(this).val() === '0')
					$('#password', $form_login).val('').prop('disabled', true); // Disabled (new customer)
				else
					$('#password', $form_login).prop('disabled', false); // Enabled
			}).find(':radio:checked').trigger('change.account');
		}*/

		// Forgot Password
/*
		var $forgot_password = $('.forgot-password', $form_login);
		if($forgot_password.size() > 0) {
			$forgot_password.popover({
				html : true,
				title: 'Forgot Password',
				content: function() {
					return $('#form-forgotpassword').html();
				}
			}).on('click.btn-forgot', function(){

				$('.btn-forgot').click(function(){
					alert('click form');
					return false;
				});

				$('.btn-close').click(function(){
					$forgot_password.popover('hide');
				});

				return false;
			});
		}
*/

		//.Form Filters
		$('#form-filters').each(function(){
			var $form = $(this);

			$form
			.on('change.filter', ':checkbox', function(){
				$form.submit();
			})
			.find('.group-btn > .btn').addClass('sr-only');
		});

		// Product details Thumbnails
		$('#product-gallery').each(function(){
			var $thumbnails = $('.thumbnail', this),
				$image = $('.product-image > img', this);

			$(this).on('click.thumbnails', '.thumbnail', function(){
				if($(this).hasClass('active'))
					return false;

				$image.attr('src',$(this).attr('href'));
				$thumbnails.removeClass('active');
				$(this).addClass('active');

				return false;
			});
		});

		// Payment Method
		$('#payment-method').each(function(){
			var $label = $('label', this);
			$label.on('change', ':radio', function(){
				$label.removeClass('active');
				$label.filter('[for="' + $(this).attr('id') + '"]').addClass('active');
			}).filter(':has(:checked)').addClass('active');
		});


		// Styliser le message Confirm par Bootstrap sur un formulaire
/*
		$('body').on('click', '[data-confirm]', function(){
			var $this = $(this);
			bootbox.confirm($(this).attr('data-confirm'),
				function(result){
					if(result) {
						// Si lien
						if($this.attr('href')){
							window.location.href = $this.attr('href');
						}else{
							// Si on doit soumettre un formulaire
							var $form = $this.closest("form");
							if($form.size() > 0){
								$form.submit();
							}
						}
					}
				}
			);

			return false;
		});
*/
	});

})(jQuery);

