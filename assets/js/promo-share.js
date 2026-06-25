/**
 * WooSync Promo Share - Grid rendering and social share functionality
 */
(function($) {
    'use strict';

    var PromoShare = {
        cache: {},
        currentFilter: 'all',
        initialized: false,

        init: function() {
            if (this.initialized) return;
            this.bindEvents();
            this.initialized = true;
        },

        bindEvents: function() {
            var self = this;

            // Filter buttons
            $(document).on('click', '.promo-filter-btn', function(e) {
                e.preventDefault();
                var filter = $(this).data('filter');
                self.setFilter(filter);
            });

            // Social share buttons
            $(document).on('click', '.share-btn', function(e) {
                e.preventDefault();
                var type = $(this).data('share');
                var productId = $(this).data('product');
                self.handleShare(type, productId);
            });

            // View product
            $(document).on('click', '.view-product-btn', function(e) {
                e.preventDefault();
                var url = $(this).data('url');
                window.open(url, '_blank');
            });

            // Refresh promos
            $(document).on('click', '#refreshPromosBtn', function(e) {
                e.preventDefault();
                self.refreshPromos();
            });
        },

        setFilter: function(filter) {
            this.currentFilter = filter;
            $('.promo-filter-btn').removeClass('active');
            $('.promo-filter-btn[data-filter="' + filter + '"]').addClass('active');
            this.renderGrid();
        },

        loadPromos: function(callback) {
            var self = this;

            if (this.cache.promos) {
                callback(this.cache.promos);
                return;
            }

            $.ajax({
                url: woosyncPromoShare.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_get_promos',
                    nonce: woosyncPromoShare.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.cache.promos = response.data;
                        callback(response.data);
                    } else {
                        self.showError('Failed to load promotions');
                    }
                },
                error: function() {
                    self.showError('Network error loading promotions');
                }
            });
        },

        renderGrid: function() {
            var self = this;
            var container = $('#promoGrid');

            if (!container.length) return;

            this.loadPromos(function(promos) {
                var filtered = self.filterPromos(promos);
                var html = '';

                if (filtered.length === 0) {
                    html = '<div class="col-12 text-center text-muted py-5">' +
                           '<h5>No promotions found for this filter</h5>' +
                           '<p>Try selecting a different category or check back later</p></div>';
                } else {
                    $.each(filtered, function(i, promo) {
                        html += self.renderCard(promo);
                    });
                }

                container.html(html);
                self.updateBadgeCount(promos.length);
            });
        },

        filterPromos: function(promos) {
            var filter = this.currentFilter;

            if (filter === 'all') return promos;

            return promos.filter(function(promo) {
                switch (filter) {
                    case 'clearance':
                        return promo.is_clearance === true;
                    case 'sale':
                        return promo.is_sale === true;
                    case 'featured':
                        return promo.is_featured === true;
                    case 'deal':
                        return promo.is_deal === true;
                    default:
                        return true;
                }
            });
        },

        renderCard: function(promo) {
            var discount = promo.discount_percent || 0;
            var campaignTag = this.getCampaignTag(promo);
            var tagClass = this.getTagClass(promo);

            var shareText = encodeURIComponent(promo.name + ' - R' + promo.sale_price + ' | Shop now: ' + promo.url + ' #promo #brandedmerch');
            var shareUrl = encodeURIComponent(promo.url);

            return '<div class="col-md-4 col-lg-3 mb-4">' +
                   '<div class="card promo-card h-100">' +
                   '<div class="promo-image-wrapper">' +
                   '<img src="' + promo.image + '" class="card-img-top promo-image" alt="' + promo.name + '" onerror="this.src=\'https://via.placeholder.com/300x200?text=No+Image\'">' +
                   '<span class="promo-tag ' + tagClass + '">' + campaignTag + '</span>' +
                   (discount > 0 ? '<span class="discount-badge">-' + discount + '%</span>' : '') +
                   '</div>' +
                   '<div class="card-body">' +
                   '<h6 class="card-title">' + promo.name + '</h6>' +
                   '<div class="promo-pricing">' +
                   '<span class="original-price">R' + promo.price + '</span>' +
                   '<span class="sale-price">R' + promo.sale_price + '</span>' +
                   '</div>' +
                   '<div class="promo-actions mt-3">' +
                   '<a href="' + promo.url + '" class="btn btn-sm btn-primary w-100 mb-2 view-product-btn" target="_blank" data-url="' + promo.url + '">View Product</a>' +
                   '<div class="share-buttons">' +
                   '<button type="button" class="btn btn-sm btn-outline-primary share-btn" data-share="facebook" data-product="' + promo.id + '" title="Share on Facebook">' +
                   '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>' +
                   '</button>' +
                   '<button type="button" class="btn btn-sm btn-outline-info share-btn" data-share="twitter" data-product="' + promo.id + '" title="Share on Twitter/X">' +
                   '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>' +
                   '</button>' +
                   '<button type="button" class="btn btn-sm btn-outline-primary share-btn" data-share="linkedin" data-product="' + promo.id + '" title="Share on LinkedIn">' +
                   '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>' +
                   '</button>' +
                   '<button type="button" class="btn btn-sm btn-outline-success share-btn" data-share="whatsapp" data-product="' + promo.id + '" title="Share on WhatsApp">' +
                   '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.218 1.365.187 1.872.114.424-.061.973-.373 1.153-.867.181-.494-.001-.741-.407-.849zM12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.178L.789 23.789l4.92-1.675C7.179 23.149 9.507 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>' +
                   '</button>' +
                   '<button type="button" class="btn btn-sm btn-outline-danger share-btn" data-share="pinterest" data-product="' + promo.id + '" title="Share on Pinterest">' +
                   '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>' +
                   '</button>' +
                   '<button type="button" class="btn btn-sm btn-outline-secondary share-btn" data-share="copy" data-product="' + promo.id + '" title="Copy Link">' +
                   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"></path></svg>' +
                   '</button>' +
                   '</div>' +
                   '</div>' +
                   '</div>' +
                   '</div>';
        },

        getCampaignTag: function(promo) {
            if (promo.is_clearance) return 'CLEARANCE';
            if (promo.is_deal) return 'DEAL OF THE DAY';
            if (promo.is_sale) return 'SALE';
            if (promo.is_featured) return 'FEATURED';
            return 'SPECIAL';
        },

        getTagClass: function(promo) {
            if (promo.is_clearance) return 'tag-clearance';
            if (promo.is_deal) return 'tag-deal';
            if (promo.is_sale) return 'tag-sale';
            if (promo.is_featured) return 'tag-featured';
            return 'tag-special';
        },

        handleShare: function(type, productId) {
            var self = this;
            var promo = this.findPromoById(productId);

            if (!promo) return;

            var url = promo.url;
            var text = promo.name + ' - R' + promo.sale_price;
            var encodedUrl = encodeURIComponent(url);
            var encodedText = encodeURIComponent(text);
            var encodedMedia = encodeURIComponent(promo.image);

            switch (type) {
                case 'facebook':
                    window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodedUrl, '_blank', 'width=600,height=400');
                    break;
                case 'twitter':
                    window.open('https://twitter.com/intent/tweet?text=' + encodedText + '&url=' + encodedUrl, '_blank', 'width=600,height=400');
                    break;
                case 'linkedin':
                    window.open('https://www.linkedin.com/sharing/share-offsite/?url=' + encodedUrl, '_blank', 'width=600,height=400');
                    break;
                case 'whatsapp':
                    window.open('https://wa.me/?text=' + encodedText + '%20' + encodedUrl, '_blank');
                    break;
                case 'pinterest':
                    window.open('https://pinterest.com/pin/create/button/?url=' + encodedUrl + '&media=' + encodedMedia + '&description=' + encodedText, '_blank', 'width=600,height=400');
                    break;
                case 'copy':
                    this.copyToClipboard(text + ' | Shop now: ' + url + ' #promo #brandedmerch');
                    break;
            }
        },

        findPromoById: function(id) {
            var promos = this.cache.promos || [];
            for (var i = 0; i < promos.length; i++) {
                if (promos[i].id == id) return promos[i];
            }
            return null;
        },

        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    alert('Link copied to clipboard!');
                }).catch(function() {
                    this.fallbackCopy(text);
                });
            } else {
                this.fallbackCopy(text);
            }
        },

        fallbackCopy: function(text) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                alert('Link copied to clipboard!');
            } catch (err) {
                alert('Failed to copy. Please copy manually.');
            }
            document.body.removeChild(textarea);
        },

        updateBadgeCount: function(count) {
            var badge = $('.promo-share-badge');
            if (badge.length) {
                badge.text('Promo Share (' + count + ')');
            }
        },

        refreshPromos: function() {
            var self = this;
            var btn = $('#refreshPromosBtn');

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Refreshing...');

            $.ajax({
                url: woosyncPromoShare.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_refresh_promos',
                    nonce: woosyncPromoShare.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.cache.promos = response.data;
                        self.renderGrid();
                        btn.prop('disabled', false).html('🔄 Refresh');
                    } else {
                        alert('Failed to refresh promotions');
                        btn.prop('disabled', false).html('🔄 Refresh');
                    }
                },
                error: function() {
                    alert('Network error');
                    btn.prop('disabled', false).html('🔄 Refresh');
                }
            });
        },

        showError: function(message) {
            var container = $('#promoGrid');
            if (container.length) {
                container.html('<div class="col-12"><div class="alert alert-danger">' + message + '</div></div>');
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        PromoShare.init();
        PromoShare.renderGrid();
    });

    // Expose globally
    window.PromoShare = PromoShare;

})(jQuery);
