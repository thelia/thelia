/*!
 * bootstrap.js v3.0.0 by @fat and @mdo
 * Copyright 2013 Twitter Inc.
 * http://www.apache.org/licenses/LICENSE-2.0
 */
if (!jQuery)throw new Error("Bootstrap requires jQuery");
+function(t) {
    "use strict";
    function e() {
        var t = document.createElement("bootstrap"), e = {WebkitTransition: "webkitTransitionEnd", MozTransition: "transitionend", OTransition: "oTransitionEnd otransitionend", transition: "transitionend"};
        for (var i in e)if (void 0 !== t.style[i])return{end: e[i]}
    }

    t.fn.emulateTransitionEnd = function(e) {
        var i = !1, n = this;
        t(this).one(t.support.transition.end, function() {
            i = !0
        });
        var o = function() {
            i || t(n).trigger(t.support.transition.end)
        };
        return setTimeout(o, e), this
    }, t(function() {
        t.support.transition = e()
    })
}(window.jQuery), +function(t) {
    "use strict";
    var e = '[data-dismiss="alert"]', i = function(i) {
        t(i).on("click", e, this.close)
    };
    i.prototype.close = function(e) {
        function i() {
            s.trigger("closed.bs.alert").remove()
        }

        var n = t(this), o = n.attr("data-target");
        o || (o = n.attr("href"), o = o && o.replace(/.*(?=#[^\s]*$)/, ""));
        var s = t(o);
        e && e.preventDefault(), s.length || (s = n.hasClass("alert") ? n : n.parent()), s.trigger(e = t.Event("close.bs.alert")), e.isDefaultPrevented() || (s.removeClass("in"), t.support.transition && s.hasClass("fade") ? s.one(t.support.transition.end, i).emulateTransitionEnd(150) : i())
    };
    var n = t.fn.alert;
    t.fn.alert = function(e) {
        return this.each(function() {
            var n = t(this), o = n.data("bs.alert");
            o || n.data("bs.alert", o = new i(this)), "string" == typeof e && o[e].call(n)
        })
    }, t.fn.alert.Constructor = i, t.fn.alert.noConflict = function() {
        return t.fn.alert = n, this
    }, t(document).on("click.bs.alert.data-api", e, i.prototype.close)
}(window.jQuery), +function(t) {
    "use strict";
    var e = function(i, n) {
        this.$element = t(i), this.options = t.extend({}, e.DEFAULTS, n)
    };
    e.DEFAULTS = {loadingText: "loading..."}, e.prototype.setState = function(t) {
        var e = "disabled", i = this.$element, n = i.is("input") ? "val" : "html", o = i.data();
        t += "Text", o.resetText || i.data("resetText", i[n]()), i[n](o[t] || this.options[t]), setTimeout(function() {
            "loadingText" == t ? i.addClass(e).attr(e, e) : i.removeClass(e).removeAttr(e)
        }, 0)
    }, e.prototype.toggle = function() {
        var t = this.$element.closest('[data-toggle="buttons"]');
        if (t.length) {
            var e = this.$element.find("input").prop("checked", !this.$element.hasClass("active")).trigger("change");
            "radio" === e.prop("type") && t.find(".active").removeClass("active")
        }
        this.$element.toggleClass("active")
    };
    var i = t.fn.button;
    t.fn.button = function(i) {
        return this.each(function() {
            var n = t(this), o = n.data("bs.button"), s = "object" == typeof i && i;
            o || n.data("bs.button", o = new e(this, s)), "toggle" == i ? o.toggle() : i && o.setState(i)
        })
    }, t.fn.button.Constructor = e, t.fn.button.noConflict = function() {
        return t.fn.button = i, this
    }, t(document).on("click.bs.button.data-api", "[data-toggle^=button]", function(e) {
        var i = t(e.target);
        i.hasClass("btn") || (i = i.closest(".btn")), i.button("toggle"), e.preventDefault()
    })
}(window.jQuery), +function(t) {
    "use strict";
    var e = function(e, i) {
        this.$element = t(e), this.$indicators = this.$element.find(".carousel-indicators"), this.options = i, this.paused = this.sliding = this.interval = this.$active = this.$items = null, "hover" == this.options.pause && this.$element.on("mouseenter", t.proxy(this.pause, this)).on("mouseleave", t.proxy(this.cycle, this))
    };
    e.DEFAULTS = {interval: 5e3, pause: "hover", wrap: !0}, e.prototype.cycle = function(e) {
        return e || (this.paused = !1), this.interval && clearInterval(this.interval), this.options.interval && !this.paused && (this.interval = setInterval(t.proxy(this.next, this), this.options.interval)), this
    }, e.prototype.getActiveIndex = function() {
        return this.$active = this.$element.find(".item.active"), this.$items = this.$active.parent().children(), this.$items.index(this.$active)
    }, e.prototype.to = function(e) {
        var i = this, n = this.getActiveIndex();
        return e > this.$items.length - 1 || 0 > e ? void 0 : this.sliding ? this.$element.one("slid", function() {
            i.to(e)
        }) : n == e ? this.pause().cycle() : this.slide(e > n ? "next" : "prev", t(this.$items[e]))
    }, e.prototype.pause = function(e) {
        return e || (this.paused = !0), this.$element.find(".next, .prev").length && t.support.transition.end && (this.$element.trigger(t.support.transition.end), this.cycle(!0)), this.interval = clearInterval(this.interval), this
    }, e.prototype.next = function() {
        return this.sliding ? void 0 : this.slide("next")
    }, e.prototype.prev = function() {
        return this.sliding ? void 0 : this.slide("prev")
    }, e.prototype.slide = function(e, i) {
        var n = this.$element.find(".item.active"), o = i || n[e](), s = this.interval, a = "next" == e ? "left" : "right", r = "next" == e ? "first" : "last", l = this;
        if (!o.length) {
            if (!this.options.wrap)return;
            o = this.$element.find(".item")[r]()
        }
        this.sliding = !0, s && this.pause();
        var h = t.Event("slide.bs.carousel", {relatedTarget: o[0], direction: a});
        if (!o.hasClass("active")) {
            if (this.$indicators.length && (this.$indicators.find(".active").removeClass("active"), this.$element.one("slid", function() {
                var e = t(l.$indicators.children()[l.getActiveIndex()]);
                e && e.addClass("active")
            })), t.support.transition && this.$element.hasClass("slide")) {
                if (this.$element.trigger(h), h.isDefaultPrevented())return;
                o.addClass(e), o[0].offsetWidth, n.addClass(a), o.addClass(a), n.one(t.support.transition.end,function() {
                    o.removeClass([e, a].join(" ")).addClass("active"), n.removeClass(["active", a].join(" ")), l.sliding = !1, setTimeout(function() {
                        l.$element.trigger("slid")
                    }, 0)
                }).emulateTransitionEnd(600)
            } else {
                if (this.$element.trigger(h), h.isDefaultPrevented())return;
                n.removeClass("active"), o.addClass("active"), this.sliding = !1, this.$element.trigger("slid")
            }
            return s && this.cycle(), this
        }
    };
    var i = t.fn.carousel;
    t.fn.carousel = function(i) {
        return this.each(function() {
            var n = t(this), o = n.data("bs.carousel"), s = t.extend({}, e.DEFAULTS, n.data(), "object" == typeof i && i), a = "string" == typeof i ? i : s.slide;
            o || n.data("bs.carousel", o = new e(this, s)), "number" == typeof i ? o.to(i) : a ? o[a]() : s.interval && o.pause().cycle()
        })
    }, t.fn.carousel.Constructor = e, t.fn.carousel.noConflict = function() {
        return t.fn.carousel = i, this
    }, t(document).on("click.bs.carousel.data-api", "[data-slide], [data-slide-to]", function(e) {
        var i, n = t(this), o = t(n.attr("data-target") || (i = n.attr("href")) && i.replace(/.*(?=#[^\s]+$)/, "")), s = t.extend({}, o.data(), n.data()), a = n.attr("data-slide-to");
        a && (s.interval = !1), o.carousel(s), (a = n.attr("data-slide-to")) && o.data("bs.carousel").to(a), e.preventDefault()
    }), t(window).on("load", function() {
        t('[data-ride="carousel"]').each(function() {
            var e = t(this);
            e.carousel(e.data())
        })
    })
}(window.jQuery), +function(t) {
    "use strict";
    var e = function(i, n) {
        this.$element = t(i), this.options = t.extend({}, e.DEFAULTS, n), this.transitioning = null, this.options.parent && (this.$parent = t(this.options.parent)), this.options.toggle && this.toggle()
    };
    e.DEFAULTS = {toggle: !0}, e.prototype.dimension = function() {
        var t = this.$element.hasClass("width");
        return t ? "width" : "height"
    }, e.prototype.show = function() {
        if (!this.transitioning && !this.$element.hasClass("in")) {
            var e = t.Event("show.bs.collapse");
            if (this.$element.trigger(e), !e.isDefaultPrevented()) {
                var i = this.$parent && this.$parent.find("> .panel > .in");
                if (i && i.length) {
                    var n = i.data("bs.collapse");
                    if (n && n.transitioning)return;
                    i.collapse("hide"), n || i.data("bs.collapse", null)
                }
                var o = this.dimension();
                this.$element.removeClass("collapse").addClass("collapsing")[o](0), this.transitioning = 1;
                var s = function() {
                    this.$element.removeClass("collapsing").addClass("in")[o]("auto"), this.transitioning = 0, this.$element.trigger("shown.bs.collapse")
                };
                if (!t.support.transition)return s.call(this);
                var a = t.camelCase(["scroll", o].join("-"));
                this.$element.one(t.support.transition.end, t.proxy(s, this)).emulateTransitionEnd(350)[o](this.$element[0][a])
            }
        }
    }, e.prototype.hide = function() {
        if (!this.transitioning && this.$element.hasClass("in")) {
            var e = t.Event("hide.bs.collapse");
            if (this.$element.trigger(e), !e.isDefaultPrevented()) {
                var i = this.dimension();
                this.$element[i](this.$element[i]())[0].offsetHeight, this.$element.addClass("collapsing").removeClass("collapse").removeClass("in"), this.transitioning = 1;
                var n = function() {
                    this.transitioning = 0, this.$element.trigger("hidden.bs.collapse").removeClass("collapsing").addClass("collapse")
                };
                return t.support.transition ? void this.$element[i](0).one(t.support.transition.end, t.proxy(n, this)).emulateTransitionEnd(350) : n.call(this)
            }
        }
    }, e.prototype.toggle = function() {
        this[this.$element.hasClass("in") ? "hide" : "show"]()
    };
    var i = t.fn.collapse;
    t.fn.collapse = function(i) {
        return this.each(function() {
            var n = t(this), o = n.data("bs.collapse"), s = t.extend({}, e.DEFAULTS, n.data(), "object" == typeof i && i);
            o || n.data("bs.collapse", o = new e(this, s)), "string" == typeof i && o[i]()
        })
    }, t.fn.collapse.Constructor = e, t.fn.collapse.noConflict = function() {
        return t.fn.collapse = i, this
    }, t(document).on("click.bs.collapse.data-api", "[data-toggle=collapse]", function(e) {
        var i, n = t(this), o = n.attr("data-target") || e.preventDefault() || (i = n.attr("href")) && i.replace(/.*(?=#[^\s]+$)/, ""), s = t(o), a = s.data("bs.collapse"), r = a ? "toggle" : n.data(), l = n.attr("data-parent"), h = l && t(l);
        a && a.transitioning || (h && h.find('[data-toggle=collapse][data-parent="' + l + '"]').not(n).addClass("collapsed"), n[s.hasClass("in") ? "addClass" : "removeClass"]("collapsed")), s.collapse(r)
    })
}(window.jQuery), +function(t) {
    "use strict";
    function e() {
        t(n).remove(), t(o).each(function(e) {
            var n = i(t(this));
            n.hasClass("open") && (n.trigger(e = t.Event("hide.bs.dropdown")), e.isDefaultPrevented() || n.removeClass("open").trigger("hidden.bs.dropdown"))
        })
    }

    function i(e) {
        var i = e.attr("data-target");
        i || (i = e.attr("href"), i = i && /#/.test(i) && i.replace(/.*(?=#[^\s]*$)/, ""));
        var n = i && t(i);
        return n && n.length ? n : e.parent()
    }

    var n = ".dropdown-backdrop", o = "[data-toggle=dropdown]", s = function(e) {
        t(e).on("click.bs.dropdown", this.toggle)
    };
    s.prototype.toggle = function(n) {
        var o = t(this);
        if (!o.is(".disabled, :disabled")) {
            var s = i(o), a = s.hasClass("open");
            if (e(), !a) {
                if ("ontouchstart"in document.documentElement && !s.closest(".navbar-nav").length && t('<div class="dropdown-backdrop"/>').insertAfter(t(this)).on("click", e), s.trigger(n = t.Event("show.bs.dropdown")), n.isDefaultPrevented())return;
                s.toggleClass("open").trigger("shown.bs.dropdown"), o.focus()
            }
            return!1
        }
    }, s.prototype.keydown = function(e) {
        if (/(38|40|27)/.test(e.keyCode)) {
            var n = t(this);
            if (e.preventDefault(), e.stopPropagation(), !n.is(".disabled, :disabled")) {
                var s = i(n), a = s.hasClass("open");
                if (!a || a && 27 == e.keyCode)return 27 == e.which && s.find(o).focus(), n.click();
                var r = t("[role=menu] li:not(.divider):visible a", s);
                if (r.length) {
                    var l = r.index(r.filter(":focus"));
                    38 == e.keyCode && l > 0 && l--, 40 == e.keyCode && l < r.length - 1 && l++, ~l || (l = 0), r.eq(l).focus()
                }
            }
        }
    };
    var a = t.fn.dropdown;
    t.fn.dropdown = function(e) {
        return this.each(function() {
            var i = t(this), n = i.data("dropdown");
            n || i.data("dropdown", n = new s(this)), "string" == typeof e && n[e].call(i)
        })
    }, t.fn.dropdown.Constructor = s, t.fn.dropdown.noConflict = function() {
        return t.fn.dropdown = a, this
    }, t(document).on("click.bs.dropdown.data-api", e).on("click.bs.dropdown.data-api", ".dropdown form",function(t) {
        t.stopPropagation()
    }).on("click.bs.dropdown.data-api", o, s.prototype.toggle).on("keydown.bs.dropdown.data-api", o + ", [role=menu]", s.prototype.keydown)
}(window.jQuery), +function(t) {
    "use strict";
    var e = function(e, i) {
        this.options = i, this.$element = t(e), this.$backdrop = this.isShown = null, this.options.remote && this.$element.load(this.options.remote)
    };
    e.DEFAULTS = {backdrop: !0, keyboard: !0, show: !0}, e.prototype.toggle = function(t) {
        return this[this.isShown ? "hide" : "show"](t)
    }, e.prototype.show = function(e) {
        var i = this, n = t.Event("show.bs.modal", {relatedTarget: e});
        this.$element.trigger(n), this.isShown || n.isDefaultPrevented() || (this.isShown = !0, this.escape(), this.$element.on("click.dismiss.modal", '[data-dismiss="modal"]', t.proxy(this.hide, this)), this.backdrop(function() {
            var n = t.support.transition && i.$element.hasClass("fade");
            i.$element.parent().length || i.$element.appendTo(document.body), i.$element.show(), n && i.$element[0].offsetWidth, i.$element.addClass("in").attr("aria-hidden", !1), i.enforceFocus();
            var o = t.Event("shown.bs.modal", {relatedTarget: e});
            n ? i.$element.find(".modal-dialog").one(t.support.transition.end,function() {
                i.$element.focus().trigger(o)
            }).emulateTransitionEnd(300) : i.$element.focus().trigger(o)
        }))
    }, e.prototype.hide = function(e) {
        e && e.preventDefault(), e = t.Event("hide.bs.modal"), this.$element.trigger(e), this.isShown && !e.isDefaultPrevented() && (this.isShown = !1, this.escape(), t(document).off("focusin.bs.modal"), this.$element.removeClass("in").attr("aria-hidden", !0).off("click.dismiss.modal"), t.support.transition && this.$element.hasClass("fade") ? this.$element.one(t.support.transition.end, t.proxy(this.hideModal, this)).emulateTransitionEnd(300) : this.hideModal())
    }, e.prototype.enforceFocus = function() {
        t(document).off("focusin.bs.modal").on("focusin.bs.modal", t.proxy(function(t) {
            this.$element[0] === t.target || this.$element.has(t.target).length || this.$element.focus()
        }, this))
    }, e.prototype.escape = function() {
        this.isShown && this.options.keyboard ? this.$element.on("keyup.dismiss.bs.modal", t.proxy(function(t) {
            27 == t.which && this.hide()
        }, this)) : this.isShown || this.$element.off("keyup.dismiss.bs.modal")
    }, e.prototype.hideModal = function() {
        var t = this;
        this.$element.hide(), this.backdrop(function() {
            t.removeBackdrop(), t.$element.trigger("hidden.bs.modal")
        })
    }, e.prototype.removeBackdrop = function() {
        this.$backdrop && this.$backdrop.remove(), this.$backdrop = null
    }, e.prototype.backdrop = function(e) {
        var i = this.$element.hasClass("fade") ? "fade" : "";
        if (this.isShown && this.options.backdrop) {
            var n = t.support.transition && i;
            if (this.$backdrop = t('<div class="modal-backdrop ' + i + '" />').appendTo(document.body), this.$element.on("click.dismiss.modal", t.proxy(function(t) {
                t.target === t.currentTarget && ("static" == this.options.backdrop ? this.$element[0].focus.call(this.$element[0]) : this.hide.call(this))
            }, this)), n && this.$backdrop[0].offsetWidth, this.$backdrop.addClass("in"), !e)return;
            n ? this.$backdrop.one(t.support.transition.end, e).emulateTransitionEnd(150) : e()
        } else!this.isShown && this.$backdrop ? (this.$backdrop.removeClass("in"), t.support.transition && this.$element.hasClass("fade") ? this.$backdrop.one(t.support.transition.end, e).emulateTransitionEnd(150) : e()) : e && e()
    };
    var i = t.fn.modal;
    t.fn.modal = function(i, n) {
        return this.each(function() {
            var o = t(this), s = o.data("bs.modal"), a = t.extend({}, e.DEFAULTS, o.data(), "object" == typeof i && i);
            s || o.data("bs.modal", s = new e(this, a)), "string" == typeof i ? s[i](n) : a.show && s.show(n)
        })
    }, t.fn.modal.Constructor = e, t.fn.modal.noConflict = function() {
        return t.fn.modal = i, this
    }, t(document).on("click.bs.modal.data-api", '[data-toggle="modal"]', function(e) {
        var i = t(this), n = i.attr("href"), o = t(i.attr("data-target") || n && n.replace(/.*(?=#[^\s]+$)/, "")), s = o.data("modal") ? "toggle" : t.extend({remote: !/#/.test(n) && n}, o.data(), i.data());
        e.preventDefault(), o.modal(s, this).one("hide", function() {
            i.is(":visible") && i.focus()
        })
    }), t(document).on("show.bs.modal", ".modal",function() {
        t(document.body).addClass("modal-open")
    }).on("hidden.bs.modal", ".modal", function() {
        t(document.body).removeClass("modal-open")
    })
}(window.jQuery), +function(t) {
    "use strict";
    var e = function(t, e) {
        this.type = this.options = this.enabled = this.timeout = this.hoverState = this.$element = null, this.init("tooltip", t, e)
    };
    e.DEFAULTS = {animation: !0, placement: "top", selector: !1, template: '<div class="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>', trigger: "hover focus", title: "", delay: 0, html: !1, container: !1}, e.prototype.init = function(e, i, n) {
        this.enabled = !0, this.type = e, this.$element = t(i), this.options = this.getOptions(n);
        for (var o = this.options.trigger.split(" "), s = o.length; s--;) {
            var a = o[s];
            if ("click" == a)this.$element.on("click." + this.type, this.options.selector, t.proxy(this.toggle, this)); else if ("manual" != a) {
                var r = "hover" == a ? "mouseenter" : "focus", l = "hover" == a ? "mouseleave" : "blur";
                this.$element.on(r + "." + this.type, this.options.selector, t.proxy(this.enter, this)), this.$element.on(l + "." + this.type, this.options.selector, t.proxy(this.leave, this))
            }
        }
        this.options.selector ? this._options = t.extend({}, this.options, {trigger: "manual", selector: ""}) : this.fixTitle()
    }, e.prototype.getDefaults = function() {
        return e.DEFAULTS
    }, e.prototype.getOptions = function(e) {
        return e = t.extend({}, this.getDefaults(), this.$element.data(), e), e.delay && "number" == typeof e.delay && (e.delay = {show: e.delay, hide: e.delay}), e
    }, e.prototype.getDelegateOptions = function() {
        var e = {}, i = this.getDefaults();
        return this._options && t.each(this._options, function(t, n) {
            i[t] != n && (e[t] = n)
        }), e
    }, e.prototype.enter = function(e) {
        var i = e instanceof this.constructor ? e : t(e.currentTarget)[this.type](this.getDelegateOptions()).data("bs." + this.type);
        return clearTimeout(i.timeout), i.hoverState = "in", i.options.delay && i.options.delay.show ? void(i.timeout = setTimeout(function() {
            "in" == i.hoverState && i.show()
        }, i.options.delay.show)) : i.show()
    }, e.prototype.leave = function(e) {
        var i = e instanceof this.constructor ? e : t(e.currentTarget)[this.type](this.getDelegateOptions()).data("bs." + this.type);
        return clearTimeout(i.timeout), i.hoverState = "out", i.options.delay && i.options.delay.hide ? void(i.timeout = setTimeout(function() {
            "out" == i.hoverState && i.hide()
        }, i.options.delay.hide)) : i.hide()
    }, e.prototype.show = function() {
        var e = t.Event("show.bs." + this.type);
        if (this.hasContent() && this.enabled) {
            if (this.$element.trigger(e), e.isDefaultPrevented())return;
            var i = this.tip();
            this.setContent(), this.options.animation && i.addClass("fade");
            var n = "function" == typeof this.options.placement ? this.options.placement.call(this, i[0], this.$element[0]) : this.options.placement, o = /\s?auto?\s?/i, s = o.test(n);
            s && (n = n.replace(o, "") || "top"), i.detach().css({top: 0, left: 0, display: "block"}).addClass(n), this.options.container ? i.appendTo(this.options.container) : i.insertAfter(this.$element);
            var a = this.getPosition(), r = i[0].offsetWidth, l = i[0].offsetHeight;
            if (s) {
                var h = this.$element.parent(), c = n, p = document.documentElement.scrollTop || document.body.scrollTop, f = "body" == this.options.container ? window.innerWidth : h.outerWidth(), u = "body" == this.options.container ? window.innerHeight : h.outerHeight(), d = "body" == this.options.container ? 0 : h.offset().left;
                n = "bottom" == n && a.top + a.height + l - p > u ? "top" : "top" == n && a.top - p - l < 0 ? "bottom" : "right" == n && a.right + r > f ? "left" : "left" == n && a.left - r < d ? "right" : n, i.removeClass(c).addClass(n)
            }
            var v = this.getCalculatedOffset(n, a, r, l);
            this.applyPlacement(v, n), this.$element.trigger("shown.bs." + this.type)
        }
    }, e.prototype.applyPlacement = function(t, e) {
        var i, n = this.tip(), o = n[0].offsetWidth, s = n[0].offsetHeight, a = parseInt(n.css("margin-top"), 10), r = parseInt(n.css("margin-left"), 10);
        isNaN(a) && (a = 0), isNaN(r) && (r = 0), t.top = t.top + a, t.left = t.left + r, n.offset(t).addClass("in");
        var l = n[0].offsetWidth, h = n[0].offsetHeight;
        if ("top" == e && h != s && (i = !0, t.top = t.top + s - h), /bottom|top/.test(e)) {
            var c = 0;
            t.left < 0 && (c = -2 * t.left, t.left = 0, n.offset(t), l = n[0].offsetWidth, h = n[0].offsetHeight), this.replaceArrow(c - o + l, l, "left")
        } else this.replaceArrow(h - s, h, "top");
        i && n.offset(t)
    }, e.prototype.replaceArrow = function(t, e, i) {
        this.arrow().css(i, t ? 50 * (1 - t / e) + "%" : "")
    }, e.prototype.setContent = function() {
        var t = this.tip(), e = this.getTitle();
        t.find(".tooltip-inner")[this.options.html ? "html" : "text"](e), t.removeClass("fade in top bottom left right")
    }, e.prototype.hide = function() {
        function e() {
            "in" != i.hoverState && n.detach()
        }

        var i = this, n = this.tip(), o = t.Event("hide.bs." + this.type);
        return this.$element.trigger(o), o.isDefaultPrevented() ? void 0 : (n.removeClass("in"), t.support.transition && this.$tip.hasClass("fade") ? n.one(t.support.transition.end, e).emulateTransitionEnd(150) : e(), this.$element.trigger("hidden.bs." + this.type), this)
    }, e.prototype.fixTitle = function() {
        var t = this.$element;
        (t.attr("title") || "string" != typeof t.attr("data-original-title")) && t.attr("data-original-title", t.attr("title") || "").attr("title", "")
    }, e.prototype.hasContent = function() {
        return this.getTitle()
    }, e.prototype.getPosition = function() {
        var e = this.$element[0];
        return t.extend({}, "function" == typeof e.getBoundingClientRect ? e.getBoundingClientRect() : {width: e.offsetWidth, height: e.offsetHeight}, this.$element.offset())
    }, e.prototype.getCalculatedOffset = function(t, e, i, n) {
        return"bottom" == t ? {top: e.top + e.height, left: e.left + e.width / 2 - i / 2} : "top" == t ? {top: e.top - n, left: e.left + e.width / 2 - i / 2} : "left" == t ? {top: e.top + e.height / 2 - n / 2, left: e.left - i} : {top: e.top + e.height / 2 - n / 2, left: e.left + e.width}
    }, e.prototype.getTitle = function() {
        var t, e = this.$element, i = this.options;
        return t = e.attr("data-original-title") || ("function" == typeof i.title ? i.title.call(e[0]) : i.title)
    }, e.prototype.tip = function() {
        return this.$tip = this.$tip || t(this.options.template)
    }, e.prototype.arrow = function() {
        return this.$arrow = this.$arrow || this.tip().find(".tooltip-arrow")
    }, e.prototype.validate = function() {
        this.$element[0].parentNode || (this.hide(), this.$element = null, this.options = null)
    }, e.prototype.enable = function() {
        this.enabled = !0
    }, e.prototype.disable = function() {
        this.enabled = !1
    }, e.prototype.toggleEnabled = function() {
        this.enabled = !this.enabled
    }, e.prototype.toggle = function(e) {
        var i = e ? t(e.currentTarget)[this.type](this.getDelegateOptions()).data("bs." + this.type) : this;
        i.tip().hasClass("in") ? i.leave(i) : i.enter(i)
    }, e.prototype.destroy = function() {
        this.hide().$element.off("." + this.type).removeData("bs." + this.type)
    };
    var i = t.fn.tooltip;
    t.fn.tooltip = function(i) {
        return this.each(function() {
            var n = t(this), o = n.data("bs.tooltip"), s = "object" == typeof i && i;
            o || n.data("bs.tooltip", o = new e(this, s)), "string" == typeof i && o[i]()
        })
    }, t.fn.tooltip.Constructor = e, t.fn.tooltip.noConflict = function() {
        return t.fn.tooltip = i, this
    }
}(window.jQuery), +function(t) {
    "use strict";
    var e = function(t, e) {
        this.init("popover", t, e)
    };
    if (!t.fn.tooltip)throw new Error("Popover requires tooltip.js");
    e.DEFAULTS = t.extend({}, t.fn.tooltip.Constructor.DEFAULTS, {placement: "right", trigger: "click", content: "", template: '<div class="popover"><div class="arrow"></div><h3 class="popover-title"></h3><div class="popover-content"></div></div>'}), e.prototype = t.extend({}, t.fn.tooltip.Constructor.prototype), e.prototype.constructor = e, e.prototype.getDefaults = function() {
        return e.DEFAULTS
    }, e.prototype.setContent = function() {
        var t = this.tip(), e = this.getTitle(), i = this.getContent();
        t.find(".popover-title")[this.options.html ? "html" : "text"](e), t.find(".popover-content")[this.options.html ? "html" : "text"](i), t.removeClass("fade top bottom left right in"), t.find(".popover-title").html() || t.find(".popover-title").hide()
    }, e.prototype.hasContent = function() {
        return this.getTitle() || this.getContent()
    }, e.prototype.getContent = function() {
        var t = this.$element, e = this.options;
        return t.attr("data-content") || ("function" == typeof e.content ? e.content.call(t[0]) : e.content)
    }, e.prototype.arrow = function() {
        return this.$arrow = this.$arrow || this.tip().find(".arrow")
    }, e.prototype.tip = function() {
        return this.$tip || (this.$tip = t(this.options.template)), this.$tip
    };
    var i = t.fn.popover;
    t.fn.popover = function(i) {
        return this.each(function() {
            var n = t(this), o = n.data("bs.popover"), s = "object" == typeof i && i;
            o || n.data("bs.popover", o = new e(this, s)), "string" == typeof i && o[i]()
        })
    }, t.fn.popover.Constructor = e, t.fn.popover.noConflict = function() {
        return t.fn.popover = i, this
    }
}(window.jQuery), +function(t) {
    "use strict";
    function e(i, n) {
        var o, s = t.proxy(this.process, this);
        this.$element = t(t(i).is("body") ? window : i), this.$body = t("body"), this.$scrollElement = this.$element.on("scroll.bs.scroll-spy.data-api", s), this.options = t.extend({}, e.DEFAULTS, n), this.selector = (this.options.target || (o = t(i).attr("href")) && o.replace(/.*(?=#[^\s]+$)/, "") || "") + " .nav li > a", this.offsets = t([]), this.targets = t([]), this.activeTarget = null, this.refresh(), this.process()
    }

    e.DEFAULTS = {offset: 10}, e.prototype.refresh = function() {
        var e = this.$element[0] == window ? "offset" : "position";
        this.offsets = t([]), this.targets = t([]);
        var i = this;
        this.$body.find(this.selector).map(function() {
            var n = t(this), o = n.data("target") || n.attr("href"), s = /^#\w/.test(o) && t(o);
            return s && s.length && [
                [s[e]().top + (!t.isWindow(i.$scrollElement.get(0)) && i.$scrollElement.scrollTop()), o]
            ] || null
        }).sort(function(t, e) {
            return t[0] - e[0]
        }).each(function() {
            i.offsets.push(this[0]), i.targets.push(this[1])
        })
    }, e.prototype.process = function() {
        var t, e = this.$scrollElement.scrollTop() + this.options.offset, i = this.$scrollElement[0].scrollHeight || this.$body[0].scrollHeight, n = i - this.$scrollElement.height(), o = this.offsets, s = this.targets, a = this.activeTarget;
        if (e >= n)return a != (t = s.last()[0]) && this.activate(t);
        for (t = o.length; t--;)a != s[t] && e >= o[t] && (!o[t + 1] || e <= o[t + 1]) && this.activate(s[t])
    }, e.prototype.activate = function(e) {
        this.activeTarget = e, t(this.selector).parents(".active").removeClass("active");
        var i = this.selector + '[data-target="' + e + '"],' + this.selector + '[href="' + e + '"]', n = t(i).parents("li").addClass("active");
        n.parent(".dropdown-menu").length && (n = n.closest("li.dropdown").addClass("active")), n.trigger("activate")
    };
    var i = t.fn.scrollspy;
    t.fn.scrollspy = function(i) {
        return this.each(function() {
            var n = t(this), o = n.data("bs.scrollspy"), s = "object" == typeof i && i;
            o || n.data("bs.scrollspy", o = new e(this, s)), "string" == typeof i && o[i]()
        })
    }, t.fn.scrollspy.Constructor = e, t.fn.scrollspy.noConflict = function() {
        return t.fn.scrollspy = i, this
    }, t(window).on("load", function() {
        t('[data-spy="scroll"]').each(function() {
            var e = t(this);
            e.scrollspy(e.data())
        })
    })
}(window.jQuery), +function(t) {
    "use strict";
    var e = function(e) {
        this.element = t(e)
    };
    e.prototype.show = function() {
        var e = this.element, i = e.closest("ul:not(.dropdown-menu)"), n = e.attr("data-target");
        if (n || (n = e.attr("href"), n = n && n.replace(/.*(?=#[^\s]*$)/, "")), !e.parent("li").hasClass("active")) {
            var o = i.find(".active:last a")[0], s = t.Event("show.bs.tab", {relatedTarget: o});
            if (e.trigger(s), !s.isDefaultPrevented()) {
                var a = t(n);
                this.activate(e.parent("li"), i), this.activate(a, a.parent(), function() {
                    e.trigger({type: "shown.bs.tab", relatedTarget: o})
                })
            }
        }
    }, e.prototype.activate = function(e, i, n) {
        function o() {
            s.removeClass("active").find("> .dropdown-menu > .active").removeClass("active"), e.addClass("active"), a ? (e[0].offsetWidth, e.addClass("in")) : e.removeClass("fade"), e.parent(".dropdown-menu") && e.closest("li.dropdown").addClass("active"), n && n()
        }

        var s = i.find("> .active"), a = n && t.support.transition && s.hasClass("fade");
        a ? s.one(t.support.transition.end, o).emulateTransitionEnd(150) : o(), s.removeClass("in")
    };
    var i = t.fn.tab;
    t.fn.tab = function(i) {
        return this.each(function() {
            var n = t(this), o = n.data("bs.tab");
            o || n.data("bs.tab", o = new e(this)), "string" == typeof i && o[i]()
        })
    }, t.fn.tab.Constructor = e, t.fn.tab.noConflict = function() {
        return t.fn.tab = i, this
    }, t(document).on("click.bs.tab.data-api", '[data-toggle="tab"], [data-toggle="pill"]', function(e) {
        e.preventDefault(), t(this).tab("show")
    })
}(window.jQuery), +function(t) {
    "use strict";
    var e = function(i, n) {
        this.options = t.extend({}, e.DEFAULTS, n), this.$window = t(window).on("scroll.bs.affix.data-api", t.proxy(this.checkPosition, this)).on("click.bs.affix.data-api", t.proxy(this.checkPositionWithEventLoop, this)), this.$element = t(i), this.affixed = this.unpin = null, this.checkPosition()
    };
    e.RESET = "affix affix-top affix-bottom", e.DEFAULTS = {offset: 0}, e.prototype.checkPositionWithEventLoop = function() {
        setTimeout(t.proxy(this.checkPosition, this), 1)
    }, e.prototype.checkPosition = function() {
        if (this.$element.is(":visible")) {
            var i = t(document).height(), n = this.$window.scrollTop(), o = this.$element.offset(), s = this.options.offset, a = s.top, r = s.bottom;
            "object" != typeof s && (r = a = s), "function" == typeof a && (a = s.top()), "function" == typeof r && (r = s.bottom());
            var l = null != this.unpin && n + this.unpin <= o.top ? !1 : null != r && o.top + this.$element.height() >= i - r ? "bottom" : null != a && a >= n ? "top" : !1;
            this.affixed !== l && (this.unpin && this.$element.css("top", ""), this.affixed = l, this.unpin = "bottom" == l ? o.top - n : null, this.$element.removeClass(e.RESET).addClass("affix" + (l ? "-" + l : "")), "bottom" == l && this.$element.offset({top: document.body.offsetHeight - r - this.$element.height()}))
        }
    };
    var i = t.fn.affix;
    t.fn.affix = function(i) {
        return this.each(function() {
            var n = t(this), o = n.data("bs.affix"), s = "object" == typeof i && i;
            o || n.data("bs.affix", o = new e(this, s)), "string" == typeof i && o[i]()
        })
    }, t.fn.affix.Constructor = e, t.fn.affix.noConflict = function() {
        return t.fn.affix = i, this
    }, t(window).on("load", function() {
        t('[data-spy="affix"]').each(function() {
            var e = t(this), i = e.data();
            i.offset = i.offset || {}, i.offsetBottom && (i.offset.bottom = i.offsetBottom), i.offsetTop && (i.offset.top = i.offsetTop), e.affix(i)
        })
    })
}(window.jQuery), /*!
 * mustache.js - Logic-less {{mustache}} templates with JavaScript
 * http://github.com/janl/mustache.js
 */
    function(t, e) {
        if ("object" == typeof exports && exports)e(exports); else {
            var i = {};
            e(i), "function" == typeof define && define.amd ? define(i) : t.Mustache = i
        }
    }(this, function(t) {
        function e(t, e) {
            return b.call(t, e)
        }

        function i(t) {
            return!e(v, t)
        }

        function n(t) {
            return t.replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g, "\\$&")
        }

        function o(t) {
            return String(t).replace(/[&<>"'\/]/g, function(t) {
                return C[t]
            })
        }

        function s(t) {
            this.string = t, this.tail = t, this.pos = 0
        }

        function a(t, e) {
            this.view = t || {}, this.parent = e, this._cache = {}
        }

        function r() {
            this.clearCache()
        }

        function l(e, i, n, o) {
            for (var s, a, r, h = "", c = 0, p = e.length; p > c; ++c)switch (s = e[c], a = s[1], s[0]) {
                case"#":
                    if (r = n.lookup(a), "object" == typeof r)if ($(r))for (var f = 0, u = r.length; u > f; ++f)h += l(s[4], i, n.push(r[f]), o); else r && (h += l(s[4], i, n.push(r), o)); else if ("function" == typeof r) {
                        var d = null == o ? null : o.slice(s[3], s[5]);
                        r = r.call(n.view, d, function(t) {
                            return i.render(t, n)
                        }), null != r && (h += r)
                    } else r && (h += l(s[4], i, n, o));
                    break;
                case"^":
                    r = n.lookup(a), (!r || $(r) && 0 === r.length) && (h += l(s[4], i, n, o));
                    break;
                case">":
                    r = i.getPartial(a), "function" == typeof r && (h += r(n));
                    break;
                case"&":
                    r = n.lookup(a), null != r && (h += r);
                    break;
                case"name":
                    r = n.lookup(a), null != r && (h += t.escape(r));
                    break;
                case"text":
                    h += a
            }
            return h
        }

        function h(t) {
            for (var e, i = [], n = i, o = [], s = 0, a = t.length; a > s; ++s)switch (e = t[s], e[0]) {
                case"#":
                case"^":
                    o.push(e), n.push(e), n = e[4] = [];
                    break;
                case"/":
                    var r = o.pop();
                    r[5] = e[2], n = o.length > 0 ? o[o.length - 1][4] : i;
                    break;
                default:
                    n.push(e)
            }
            return i
        }

        function c(t) {
            for (var e, i, n = [], o = 0, s = t.length; s > o; ++o)e = t[o], e && ("text" === e[0] && i && "text" === i[0] ? (i[1] += e[1], i[3] = e[3]) : (i = e, n.push(e)));
            return n
        }

        function p(t) {
            return[new RegExp(n(t[0]) + "\\s*"), new RegExp("\\s*" + n(t[1]))]
        }

        function f(e, o) {
            function a() {
                if (T && !E)for (; x.length;)delete k[x.pop()]; else x = [];
                T = !1, E = !1
            }

            if (e = e || "", o = o || t.tags, "string" == typeof o && (o = o.split(d)), 2 !== o.length)throw new Error("Invalid tags: " + o.join(", "));
            for (var r, l, f, v, b, w = p(o), $ = new s(e), C = [], k = [], x = [], T = !1, E = !1; !$.eos();) {
                if (r = $.pos, f = $.scanUntil(w[0]))for (var D = 0, S = f.length; S > D; ++D)v = f.charAt(D), i(v) ? x.push(k.length) : E = !0, k.push(["text", v, r, r + 1]), r += 1, "\n" == v && a();
                if (!$.scan(w[0]))break;
                if (T = !0, l = $.scan(y) || "name", $.scan(u), "=" === l ? (f = $.scanUntil(m), $.scan(m), $.scanUntil(w[1])) : "{" === l ? (f = $.scanUntil(new RegExp("\\s*" + n("}" + o[1]))), $.scan(g), $.scanUntil(w[1]), l = "&") : f = $.scanUntil(w[1]), !$.scan(w[1]))throw new Error("Unclosed tag at " + $.pos);
                if (b = [l, f, r, $.pos], k.push(b), "#" === l || "^" === l)C.push(b); else if ("/" === l) {
                    if (0 === C.length)throw new Error('Unopened section "' + f + '" at ' + r);
                    var j = C.pop();
                    if (j[1] !== f)throw new Error('Unclosed section "' + j[1] + '" at ' + r)
                } else if ("name" === l || "{" === l || "&" === l)E = !0; else if ("=" === l) {
                    if (o = f.split(d), 2 !== o.length)throw new Error("Invalid tags at " + r + ": " + o.join(", "));
                    w = p(o)
                }
            }
            var j = C.pop();
            if (j)throw new Error('Unclosed section "' + j[1] + '" at ' + $.pos);
            return k = c(k), h(k)
        }

        var u = /\s*/, d = /\s+/, v = /\S/, m = /\s*=/, g = /\s*\}/, y = /#|\^|\/|>|\{|&|=|!/, b = RegExp.prototype.test, w = Object.prototype.toString, $ = Array.isArray || function(t) {
            return"[object Array]" === w.call(t)
        }, C = {"&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;", "/": "&#x2F;"};
        s.prototype.eos = function() {
            return"" === this.tail
        }, s.prototype.scan = function(t) {
            var e = this.tail.match(t);
            return e && 0 === e.index ? (this.tail = this.tail.substring(e[0].length), this.pos += e[0].length, e[0]) : ""
        }, s.prototype.scanUntil = function(t) {
            var e, i = this.tail.search(t);
            switch (i) {
                case-1:
                    e = this.tail, this.pos += this.tail.length, this.tail = "";
                    break;
                case 0:
                    e = "";
                    break;
                default:
                    e = this.tail.substring(0, i), this.tail = this.tail.substring(i), this.pos += i
            }
            return e
        }, a.make = function(t) {
            return t instanceof a ? t : new a(t)
        }, a.prototype.push = function(t) {
            return new a(t, this)
        }, a.prototype.lookup = function(t) {
            var e = this._cache[t];
            if (!e) {
                if ("." == t)e = this.view; else for (var i = this; i;) {
                    if (t.indexOf(".") > 0) {
                        e = i.view;
                        for (var n = t.split("."), o = 0; e && o < n.length;)e = e[n[o++]]
                    } else e = i.view[t];
                    if (null != e)break;
                    i = i.parent
                }
                this._cache[t] = e
            }
            return"function" == typeof e && (e = e.call(this.view)), e
        }, r.prototype.clearCache = function() {
            this._cache = {}, this._partialCache = {}
        }, r.prototype.compile = function(e, i) {
            var n = this._cache[e];
            if (!n) {
                var o = t.parse(e, i);
                n = this._cache[e] = this.compileTokens(o, e)
            }
            return n
        }, r.prototype.compilePartial = function(t, e, i) {
            var n = this.compile(e, i);
            return this._partialCache[t] = n, n
        }, r.prototype.getPartial = function(t) {
            return t in this._partialCache || !this._loadPartial || this.compilePartial(t, this._loadPartial(t)), this._partialCache[t]
        }, r.prototype.compileTokens = function(t, e) {
            var i = this;
            return function(n, o) {
                if (o)if ("function" == typeof o)i._loadPartial = o; else for (var s in o)i.compilePartial(s, o[s]);
                return l(t, i, a.make(n), e)
            }
        }, r.prototype.render = function(t, e, i) {
            return this.compile(t)(e, i)
        }, t.name = "mustache.js", t.version = "0.7.2", t.tags = ["{{", "}}"], t.Scanner = s, t.Context = a, t.Writer = r, t.parse = f, t.escape = o;
        var k = new r;
        t.clearCache = function() {
            return k.clearCache()
        }, t.compile = function(t, e) {
            return k.compile(t, e)
        }, t.compilePartial = function(t, e, i) {
            return k.compilePartial(t, e, i)
        }, t.compileTokens = function(t, e) {
            return k.compileTokens(t, e)
        }, t.render = function(t, e, i) {
            return k.render(t, e, i)
        }, t.to_html = function(e, i, n, o) {
            var s = t.render(e, i, n);
            return"function" != typeof o ? s : void o(s)
        }
    }), function(t) {
    function e(t) {
        var e = t.match(/^.*(youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/);
        return e && 11 === e[2].length ? e[2] : !1
    }

    function i(t, i) {
        var n = e(t);
        return t && n && (t = "https://www.youtube.com/" + (i ? "embed/" : "v/") + e(t)), t
    }

    function n(t, e, i, n) {
        var o, s;
        return n && (o = 'width="' + e + '" height="' + i + '"', s = t ? '<iframe src="' + n + '" ' + o + ' frameborder="0" allowfullscreen>&nbsp;</iframe>' : '<div class="youtube"><object type="application/x-shockwave-flash" ' + o + ' data="' + n + '&modestbranding=1"><param name="movie" value="' + n + '&modestbranding=1" /><param name="wmode" value="transparent" /></object></div>'), s
    }

    function o() {
        var e, o = "", s = t("#video").is(":checked"), a = t("#youtubeAutoplay").is(":checked"), r = t("#youtubeREL").is(":checked"), l = t("#youtubeHD").is(":checked"), h = t("#youtubeWidth").val(), c = t("#youtubeHeight").val(), p = i(t("#youtubeID").val(), s);
        return a && (o += "&amp;autoplay=1"), r && (o += "&amp;rel=0"), l && (o += "&amp;hd=1"), p && (e = n(s, h, c, p + (s ? "" : o))), e
    }

    function s() {
        t("#preview").html(n(!0, 420, 315, i(t("#youtubeID").val())))
    }

    function a(t) {
        clearTimeout(h), h = setTimeout(s, t || 1e3)
    }

    function r() {
        var t = o();
        t && parent.tinymce.activeEditor.insertContent(t), parent.tinymce.activeEditor.windowManager.close()
    }

    function l() {
        t("#preview").length && t("#youtubeID").keypress(function() {
            a()
        }).change(function() {
            a(100)
        })
    }

    var h;
    t(function() {
        var e = {youtubeurl: parent.tinymce.util.I18n.translate("Youtube URL"), youtubeID: parent.tinymce.util.I18n.translate("Youtube ID"), youtubeWidth: parent.tinymce.util.I18n.translate("width"), youtubeHeight: parent.tinymce.util.I18n.translate("height"), youtubeAutoplay: parent.tinymce.util.I18n.translate("autoplay"), youtubeHD: parent.tinymce.util.I18n.translate("HD video"), youtubeREL: parent.tinymce.util.I18n.translate("Related video"), HTML5: parent.tinymce.util.I18n.translate("HTML5"), Insert: parent.tinymce.util.I18n.translate("Insert")};
        t.get("view/forms.html", function(i) {
            t("#template-container").append(Mustache.render(i, e)), l(), t("#insert-btn").on("click", r)
        })
    })
}(jQuery);