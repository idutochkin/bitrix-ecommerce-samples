/*
BX.PULL.capturePullEvent();
BX.PULL.getDebugInfo();
*/

let app = new Vue({
    el: "#app",
    name: 'root',
    data: function() {
        return {
            moduleId: winnnnerData.moduleId,
            platform: winnnnerData.platform,
            agreements: winnnnerData.platform.agreements,
            user: winnnnerData.user,
            game: winnnnerData.game,
            components2: winnnnerData.components,
            usersOnline: {
                value: winnnnerData.game.currentGame.usersOnline,
                stepValue: winnnnerData.game.currentGame.usersOnline,
                tweenedValue: winnnnerData.game.currentGame.usersOnline
            },
            currentFund: {
                value: winnnnerData.game.currentGame.fund,
                stepValue: winnnnerData.game.currentGame.fund,
                tweenedValue: winnnnerData.game.currentGame.fund
            },
            training: false,
            isBlocked: false,
            onAfterAuth: false,
            volume: true,
            rotateDisplay: false,
            showRating: false,
            rating: [],
            popupData: {
                title: "",
                body: ""
            },
            timer: {
                id: 0,
                interval: 1000,
                hours: '00',
                minutes: '00',
                seconds: '00',
            },
            navigation: {
                active: false
            },
            components: {
                timerHead: {
                    active: false
                },
                walletHead: {
                    active: false
                },
                blocked: {
                    active: false
                },
                popup: {
                    active: false
                },
                startView: {
                    active: true
                },
                startViewBlocked: {
                    active: false
                },
                level: {
                    active: false
                },
                finish: {
                    active: false
                },
                agreement: {
                    active: false
                },
                auth: {
                    active: false
                },
                personal: {
                    active: false,
                    results: {
                        active: false
                    },
                    allresults: {
                        active: false
                    },
                    subscribe: {
                        active: false
                    },
                    wallet: {
                        active: false
                    },
                    games: {
                        active: false
                    },
                    notifications: {
                        active: false
                    },
                    gifts: {
                        active: false
                    },
                    profile: {
                        active: false
                    },
                    referrals: {
                        active: false
                    },
                    agreements: {
                        active: false
                    }
                }
            }
        }
    },
    mounted: function() {
        this.init();
    },
    watch: {
        'currentFund.value': function (newValue) {
            let $this = this;

            gsap.to($this.currentFund, {
                duration: 1.5, stepValue: newValue, onUpdate: () => {
                    $this.currentFund.tweenedValue = $this.toIntFormat($this.currentFund.stepValue);
                }
            });
        },
        'usersOnline.value': function (newValue) {
            let $this = this;

            gsap.to($this.usersOnline, {
                duration: 1.5, stepValue: newValue, onUpdate: () => {
                    $this.usersOnline.tweenedValue = $this.toIntFormat($this.usersOnline.stepValue);
                }
            });
        }
    },
    methods: {
        init() {
            let $this = this;

            $this.$el.style = 'display:block';

            BX.addCustomEvent("onPullEvent", (moduleId, command, params) => {
                if (moduleId !== $this.moduleId)
                    return;

                command = command[0].toUpperCase() + command.slice(1);

                if ($this['pull' + command] instanceof Function)
                    $this['pull' + command]($this, params);
            });

            this.callAction('GameController.getRating', null, (result) => {
                if (result["error"] !== undefined) {
                } else {
                    $this.rating = [];

                    result.forEach((rate) => {
                        var duration = moment.duration(Number(rate.time));
                        var minutes = duration.minutes();
                        var seconds = duration.seconds();
                        var milliseconds = duration.milliseconds();
                        milliseconds = (milliseconds < 100 ? (milliseconds < 10 ? '00' : '0') : '') + milliseconds;

                        rate.time = (minutes < 10 ? "0" + minutes : minutes) + ":" + (seconds < 10 ? "0" + seconds : seconds) + "." + milliseconds;

                        $this.rating.push(rate);

                        if (rate.userId === $this.user.id) {
                            $this.user.currentGame.result = rate.time;
                            $this.user.currentGame.rank = rate.rate;
                            $this.user.currentGame.gain = rate.prize;
                        }
                    });
                }
            });

            window.addEventListener("orientationchange", function () {
                if ($this.components.level.active || $this.components.finish.active || $this.components.agreement.active) {
                    if (window.innerHeight > window.innerWidth)
                        $this.rotateDisplay = true;
                    else {
                        $this.rotateDisplay = false;

                        if ($this.components.level.active)
                            $this.$refs.levelComponent.countdownGameRestart();
                    }
                }
            }, false);

            window.addEventListener('load', function () {
                AOS.init();

                new MutationObserver(function (mutationsList, observer) {
                    AOS.init();
                }).observe(document.getElementById('app'), {
                    childList: true
                });
            });

            if (this.game.currentGame.distributionTime)
                this.stopGame(true);

            this.initTimer();

            let copyText = document.querySelectorAll('.js_copy-text');
            if (copyText != null)
                copyText.forEach(node => $this.copyText(node));
        },
        pullUsersOnline($this, params) {
            $this.usersOnline.value = params.count[$this.game.id];
        },
        pullFundGame($this, params) {
            $this.currentFund.value += params.value;
        },
        pullIsBlocked($this, params) {
            $this.isBlocked = true;
            $this.showComponent();
        },
        pullSyncTimer($this, params) {
            $this.game.accessLastTime = params.accessLastTime;
            $this.game.accessGameByTime = params.accessGameByTime;

            clearInterval($this.timer.id);
            $this.initTimer();
        },
        pullNotify($this, params) {
            $this.showPopup(params.title, params.body);
        },
        pullReload($this, params) {
            location.reload();
        },
        toIntFormat(int = 0) {
            return int.toFixed(0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        },
        isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|BB|PlayBook|IEMobile|Windows Phone|Kindle|Silk|Opera Mini/i.test(navigator.userAgent);
        },
        finishGame() {
            this.showComponent("finish");
        },
        stopGame(byTime = false) {
            document.querySelector('body').classList.remove('fullscreen');

            if (byTime)
                this.showPopup("Игра окончена", "Игра окончена. Идет распределения выигрыша.");

            if (!this.components['startView'].active)
                this.showComponent("startView");
        },
        menuShow() {
            if (this.user.isAuthorized) {
                this.navigation.active = !this.navigation.active;
                if (!this.navigation.active)
                    this.showPersonal();
            } else {
                this.onAfterAuth = function () {
                    this.navigation.active = !this.navigation.active;
                };

                this.showComponent("auth", false);
            }
        },
        showComponent(selectedComponent = false, closeOther = true) {
            if (this.game.currentGame.distributionTime) {
                this.stopGame(true);
                return;
            }

            if (this.isBlocked)
                selectedComponent = 'blocked';

            if (closeOther) {
                for (var component in this.components) {
                    if (component == selectedComponent || component == 'popup')
                        continue;

                    this.components[component].active = false;
                }
            }

            if (selectedComponent)
                this.components[selectedComponent].active = true;
        },
        showPersonal(selectedComponent = false, closeOther = true) {
            if (this.game.currentGame.distributionTime) {
                this.stopGame(true);
                return;
            }

            if (this.isBlocked) {
                this.showComponent();
                return;
            }

            if (this.user.isAuthorized) {
                if (closeOther) {
                    for (var component in this.components.personal) {
                        if (component == selectedComponent)
                            continue;

                        this.components.personal[component].active = false;
                    }
                }

                if (selectedComponent) {
                    this.components.personal[selectedComponent].active = true;
                    this.navigation.active = true;
                    this.components.personal.active = true;
                } else
                    this.components.personal.active = false;
            } else {
                this.onAfterAuth = function () {
                    if (closeOther) {
                        for (var component in this.components.personal) {
                            if (component == selectedComponent)
                                continue;

                            this.components.personal[component].active = false;
                        }
                    }

                    if (selectedComponent) {
                        this.components.personal[selectedComponent].active = true;
                        this.navigation.active = true;
                        this.components.personal.active = true;
                    } else {
                        this.navigation.active = false;
                        this.components.personal.active = false;
                    }
                };

                this.showComponent("auth", false);
            }
        },
        logout() {
            this.callAction('UserController.logout', null, (result) => location.reload());
        },
        showPopup(title, body, callback = () => {}) {
            this.popupData.title = title;
            this.popupData.body = body;
            this.popupData.callback = callback;

            this.components.popup.active = true;
        },
        showNotify(message, toAppend, typeAppend = 'before') {
            let parent = document.querySelector(toAppend);

            let notify = parent.parentElement.querySelector('.notify');
            if (notify == null) {
                notify = document.createElement('div');
                notify.classList.add('notify');
                notify.classList.add('payment__message');

                switch (typeAppend) {
                    case 'after':
                        parent.after(notify);
                        break;
                    case 'before':
                        parent.before(notify);
                        break;
                }
            }
            notify.innerText = message;

            setTimeout(() => {
                notify.style.opacity = 0;
            }, 2000);
            setTimeout(() => {
                notify.remove();
            }, 3100);
        },
        callAction(action, data, callback = () => {}) {
            BX.ajax.runAction(this.moduleId.replace('.', ':') + '.' + action, {
                data: data
            }).then(
                resolve => callback(resolve.data),
                reject => console.error(reject)
            );
        },
        initTimer() {
            let $this = this;
            let params = $this.timer;
            let duration = this.getTimerDuration();

            $this.timer.id = setInterval(() => {
                duration = moment.duration(duration - params.interval, 'milliseconds');

                $this.timer.hours = String(duration.hours() < 10 ? "0" + duration.hours() : duration.hours()).split("");
                $this.timer.minutes = String(duration.minutes() < 10 ? "0" + duration.minutes() : duration.minutes()).split("");
                $this.timer.seconds = String(duration.seconds() < 10 ? "0" + duration.seconds() : duration.seconds()).split("");

                if($this.game.accessLastTime < 0) {
                    if(!$this.game.accessGameByTime)
                        $this.game.accessGameByTime = true;
                } else if($this.game.accessGameByTime)
                    $this.game.accessGameByTime = false;

                if(
                    $this.timer.hours[0] + $this.timer.hours[1] === "00"
                    && $this.timer.minutes[0] + $this.timer.minutes[1] === "00"
                    && $this.timer.seconds[0] + $this.timer.seconds[1] === "00"
                )
                    location.reload();
            }, params.interval);
        },
        getTimerDuration() {
            let currentTime = moment().unix();
            let eventTime = currentTime + (this.game.accessLastTime < 0 ? this.game.accessLastTime * -1 : this.game.accessLastTime);
            let diffTime = eventTime - currentTime;

            return moment.duration(diffTime * this.timer.interval, 'milliseconds');
        },
        copyText(node) {
            navigator.clipboard.writeText(node.innerText);
            alert(node.dataset.successText);
        }
    }
});