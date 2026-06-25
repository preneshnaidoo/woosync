/**
 * WooSync Promo Share - JavaScript Handler
 * Handles promo grid rendering, filtering, and social sharing
 */

(function($) {
    'use strict';

    const PromoShare = {
        currentFilter: 'all',
        promos: [],
        
        init: function() {
            this.bindEvents();
            this.loadPromos();
        },
        
        bindEvents: function() {
            // Filter buttons
            $(document).on('click', '.promo-filter-btn', (e) => {
                const filter = $(e.currentTarget).data('filter');
                this.setFilter(filter);
            });
            
            // Share buttons
            $(document).on('click', '.share-btn', (e) => {
                e.preventDefault();
                const type = $(e.currentTarget).data('share');
                const productCode = $(e.currentTarget).closest('.promo-card').data('product-code');
                this.shareProduct(type, productCode);
            });
            
            // Copy link button
            $(document).on('click', '.copy-link-btn', (e) => {
                e.preventDefault();
                const productCode = $(e.currentTarget).closest('.promo-card').data('product-code');
                this.copyLink(productCode);
            });
            
            // Send email button
            $(document).on('click', '.send-email-btn', (e) => {
                e.preventDefault();
                const productCode = $(e.currentTarget).closest('.promo-card').data('product-code');
                this.showEmailModal(productCode);
            });
            
            // Refresh button
            $(document).on('click', '#refreshPromosBtn', () => {
                this.loadPromos(true);
            });
            
            // Email form submission
            $(document).on('submit', '#promoEmailForm', (e) => {
                e.preventDefault();
                this.sendPromoEmail();
            });
        },
        
        loadPromos: function(forceRefresh = false) {
            const $btn = $('#refreshPromosBtn');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Loading...');
            
            $.ajax({
                url: amrodSyncData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_fetch_promos',
                    nonce: amrodSyncData.nonce,
                    force_refresh: forceRefresh ? 1 : 0
                },
                success: (response) => {
                    if (response.success) {
                        this.promos = response.data.promos;
                        this.renderPromoGrid();
                        this.updateCountBadge();
                    } else {
                        this.showError(response.data || 'Failed to load promos');
                    }
                },
                error: () => {
                    this.showError('Network error loading promos');
                },
                complete: () => {
                    $btn.prop('disabled', false).html('<i class="bi bi-arrow-clockwise me-1"></i>Refresh');
                }
            });
        },
        
        setFilter: function(filter) {
            this.currentFilter = filter;
            $('.promo-filter-btn').removeClass('active');
            $(`.promo-filter-btn[data-filter="${filter}"]`).addClass('active');
            this.renderPromoGrid();
        },
        
        renderPromoGrid: function() {
            const $grid = $('#promoGrid');
            const $hero = $('#promoHero');
            
            // Filter promos
            let filteredPromos = this.promos;
            if (this.currentFilter !== 'all') {
                filteredPromos = this.promos.filter(p => p.campaign_type === this.currentFilter);
            }
            
            // Hero banner (first promo with banner image)
            const heroPromo = this.promos.find(p => p.banner_image) || this.promos[0];
            if (heroPromo) {
                $hero.html(this.renderHeroBanner(heroPromo));
            } else {
                $hero.html('<div class="promo-hero-placeholder"><p class="text-muted">No active promos found</p></div>');
            }
            
            // Promo grid
            if (filteredPromos.length === 0) {
                $grid.html('<div class="col-12 text-center py-5"><p class="text-muted">No promos match this filter</p></div>');
                return;
            }
            
            let html = '';
            filteredPromos.forEach(promo => {
                html += this.renderPromoCard(promo);
            });
            $grid.html(html);
        },
        
        renderHeroBanner: function(promo) {
            const imageUrl = promo.banner_image || promo.hero_image || promo.marketing_image || '';
            const tag = this.getCampaignTag(promo);
            const tagClass = this.getCampaignTagClass(promo);
            
            return `
                <div class="promo-hero" style="${imageUrl ? `background-image: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), url('${imageUrl}');` : 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);'}">
                    <div class="promo-hero-content">
                        <span class="promo-hero-tag ${tagClass}">${tag}</span>
                        <h2 class="promo-hero-title">${promo.name || 'Featured Promotion'}</h2>
                        ${promo.sale_price && promo.price ? `
                            <div class="promo-hero-pricing">
                                <span class="original-price">R${parseFloat(promo.price).toFixed(2)}</span>
                                <span class="sale-price">R${parseFloat(promo.sale_price).toFixed(2)}</span>
                                <span class="discount-badge">-${Math.round((1 - promo.sale_price / promo.price) * 100)}% OFF</span>
                            </div>
                        ` : ''}
                        <a href="${promo.product_url || '#'}" target="_blank" class="btn btn-light btn-lg mt-3">
                            <i class="bi bi-box-arrow-up-right me-2"></i>View Product
                        </a>
                    </div>
                </div>
            `;
        },
        
        renderPromoCard: function(promo) {
            const imageUrl = promo.image || promo.banner_image || promo.hero_image || 'https://via.placeholder.com/300x200?text=No+Image';
            const tag = this.getCampaignTag(promo);
            const tagClass = this.getCampaignTagClass(promo);
            const hasDiscount = promo.sale_price && promo.price && promo.sale_price < promo.price;
            const discountPercent = hasDiscount ? Math.round((1 - promo.sale_price / promo.price) * 100) : 0;
            const isNew = promo.is_new;
            const hasEnded = promo.campaign_end && new Date(promo.campaign_end) < new Date();
            
            return `
                <div class="col-md-6 col-lg-4 mb-4" data-product-code="${promo.product_code}">
                    <div class="promo-card ${hasEnded ? 'promo-ended' : ''}">
                        <div class="promo-card-image">
                            <img src="${imageUrl}" alt="${promo.name}" onerror="this.src='https://via.placeholder.com/300x200?text=No+Image'">
                            <div class="promo-card-badges">
                                ${tag ? `<span class="promo-tag ${tagClass}">${tag}</span>` : ''}
                                ${isNew ? '<span class="promo-tag promo-tag-new">✨ NEW</span>' : ''}
                                ${hasDiscount ? `<span class="promo-tag promo-tag-discount">-${discountPercent}%</span>` : ''}
                            </div>
                        </div>
                        <div class="promo-card-body">
                            <h5 class="promo-card-title">${promo.name || 'Unknown Product'}</h5>
                            <p class="promo-card-sku text-muted small">${promo.sku || ''}</p>
                            
                            <div class="promo-card-pricing">
                                ${hasDiscount ? `
                                    <span class="original-price">R${parseFloat(promo.price).toFixed(2)}</span>
                                    <span class="sale-price">R${parseFloat(promo.sale_price).toFixed(2)}</span>
                                ` : `
                                    <span class="sale-price">R${parseFloat(promo.price || 0).toFixed(2)}</span>
                                `}
                            </div>
                            
                            ${promo.campaign_end ? `
                                <p class="promo-card-end small text-muted mb-3">
                                    <i class="bi bi-clock me-1"></i>
                                    ${hasEnded ? 'Campaign ended' : 'Ends: ' + promo.campaign_end}
                                </p>
                            ` : ''}
                            
                            <div class="promo-card-actions">
                                <a href="${promo.product_url || '#'}" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>View
                                </a>
                                <button type="button" class="btn btn-outline-secondary btn-sm send-email-btn" data-product-code="${promo.product_code}">
                                    <i class="bi bi-envelope me-1"></i>Email
                                </button>
                            </div>
                            
                            <div class="promo-share-buttons">
                                <button type="button" class="share-btn" data-share="facebook" title="Share on Facebook">
                                    <i class="bi bi-facebook"></i>
                                </button>
                                <button type="button" class="share-btn" data-share="twitter" title="Share on Twitter/X">
                                    <i class="bi bi-twitter-x"></i>
                                </button>
                                <button type="button" class="share-btn" data-share="linkedin" title="Share on LinkedIn">
                                    <i class="bi bi-linkedin"></i>
                                </button>
                                <button type="button" class="share-btn" data-share="whatsapp" title="Share on WhatsApp">
                                    <i class="bi bi-whatsapp"></i>
                                </button>
                                <button type="button" class="share-btn" data-share="pinterest" title="Share on Pinterest">
                                    <i class="bi bi-pinterest"></i>
                                </button>
                                <button type="button" class="share-btn" data-share="instagram" title="Share on Instagram">
                                    <i class="bi bi-instagram"></i>
                                </button>
                                <button type="button" class="copy-link-btn" title="Copy Link">
                                    <i class="bi bi-link-45deg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },
        
        getCampaignTag: function(promo) {
            if (promo.clearance) return '🔥 CLEARANCE';
            if (promo.deal_of_day) return '💰 DEAL OF THE DAY';
            if (promo.on_sale) return '🏷️ SALE';
            if (promo.featured) return '⭐ FEATURED';
            if (promo.special) return '✨ SPECIAL';
            return '';
        },
        
        getCampaignTagClass: function(promo) {
            if (promo.clearance) return 'promo-tag-clearance';
            if (promo.deal_of_day) return 'promo-tag-deal';
            if (promo.on_sale) return 'promo-tag-sale';
            if (promo.featured) return 'promo-tag-featured';
            if (promo.special) return 'promo-tag-special';
            return '';
        },
        
        shareProduct: function(type, productCode) {
            const promo = this.promos.find(p => p.product_code === productCode);
            if (!promo) return;
            
            const url = encodeURIComponent(promo.product_url || window.location.href);
            const name = encodeURIComponent(promo.name || 'Product');
            const price = `R${parseFloat(promo.price || promo.sale_price || 0).toFixed(2)}`;
            const image = encodeURIComponent(promo.image || promo.banner_image || '');
            
            let shareUrl = '';
            
            switch (type) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?text=${name} - ${price}&url=${url}`;
                    break;
                case 'linkedin':
                    shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
                    break;
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${name} - ${price} ${url}`;
                    break;
                case 'pinterest':
                    shareUrl = `https://pinterest.com/pin/create/button/?url=${url}&media=${image}&description=${name}`;
                    break;
                case 'instagram':
                    // Instagram web intent - opens app if installed
                    shareUrl = `instagram://share?text=${name} - ${price} ${url}`;
                    // Fallback to copy for web users
                    this.copyToClipboard(`${promo.name} - ${price} | Shop now: ${promo.product_url || url} #promo #brandedmerch`);
                    this.showToast('Instagram link copied! Open Instagram app to share.');
                    window.open(shareUrl, '_blank');
                    return;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        },
        
        copyLink: function(productCode) {
            const promo = this.promos.find(p => p.product_code === productCode);
            if (!promo) return;
            
            const text = `${promo.name} - R${parseFloat(promo.price || promo.sale_price || 0).toFixed(2)} | Shop now: ${promo.product_url || window.location.href} #promo #brandedmerch`;
            this.copyToClipboard(text);
            this.showToast('Link copied to clipboard!');
        },
        
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
        },
        
        showEmailModal: function(productCode) {
            const promo = this.promos.find(p => p.product_code === productCode);
            if (!promo) return;
            
            $('#emailProductName').text(promo.name || 'Unknown Product');
            $('#emailProductCode').val(productCode);
            $('#emailSubject').val(`Check out this deal: ${promo.name || 'Product'}`);
            
            // Reset recipient selection
            $('input[name="email_recipients"]').prop('checked', false);
            $('#emailUserList').hide();
            
            new bootstrap.Modal($('#promoEmailModal')).show();
        },
        
        sendPromoEmail: function() {
            const productCode = $('#emailProductCode').val();
            const subject = $('#emailSubject').val();
            const recipientType = $('input[name="email_recipients"]:checked').val();
            const userIds = $('#emailUserIds').val();
            
            const $btn = $('#sendPromoEmailBtn');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Sending...');
            
            $.ajax({
                url: amrodSyncData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_send_promo_email',
                    nonce: amrodSyncData.nonce,
                    product_code: productCode,
                    subject: subject,
                    recipient_type: recipientType,
                    user_ids: userIds
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast('Promo email sent successfully!');
                        bootstrap.Modal.getInstance($('#promoEmailModal')).hide();
                    } else {
                        this.showError(response.data || 'Failed to send email');
                    }
                },
                error: () => {
                    this.showError('Network error sending email');
                },
                complete: () => {
                    $btn.prop('disabled', false).html('<i class="bi bi-send me-2"></i>Send Email');
                }
            });
        },
        
        updateCountBadge: function() {
            const count = this.promos.length;
            $('#promoShareTabCount').text(`(${count})`);
        },
        
        showError: function(message) {
            $('#promoError').html(`<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`).show();
        },
        
        showToast: function(message) {
            const toast = $(`<div class="toast-notification">${message}</div>`);
            $('body').append(toast);
            setTimeout(() => toast.addClass('show'), 10);
            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('#promoShareTab').length) {
            PromoShare.init();
        }
    });
    
    // Toggle user list visibility based on recipient selection
    $(document).on('change', 'input[name="email_recipients"]', function() {
        if ($(this).val() === 'specific') {
            $('#emailUserList').show();
        } else {
            $('#emailUserList').hide();
        }
    });
    
})(jQuery);
