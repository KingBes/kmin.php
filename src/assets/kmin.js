// 已注册组件列表
let Comps = [];

/**
 * 注册自定义组件   
 * 
 * @param {string} tagName 标签名称
 * @param {class} comp 组件类
 * @returns {void}
 */
export function regComp(tagName, comp) {
    if (!Comps.find((item) => item === tagName)) {
        Comps.push(tagName);
        customElements.define(tagName,
            class extends KMin {
                constructor() {
                    super();
                    comp.call(this);
                    for (const attr of this.attributes) {
                        this.attred(attr.name, undefined, attr.value);
                    }
                }
            }
        );
    }
}

/**
 * 异步导入自定义组件
 * 
 * @param {string} url 组件URL
 */
export async function impComp(url) {
    let tagName = url.split('/').pop().split('.')[0];
    const comp = await import(url);
    regComp(tagName, comp.default);
}

/**
 * 自定义组件
 */
export class KMin extends HTMLElement {
    obServerAttr = new MutationObserver((mutationsList) => {
        // 遍历所有变化记录
        for (const mutation of mutationsList) {
            // 仅处理属性变化（过滤其他类型的 DOM 变化）
            if (mutation.type === 'attributes') {
                const {
                    target: el, // 发生变化的元素
                    attributeName, // 变化的属性名（如 class、id、data-info）
                    oldValue, // 变化前的旧值（需开启 attributeOldValue: true）
                } = mutation;
                // 获取变化后的新值
                const newValue = el.getAttribute(attributeName);
                this.attred(attributeName, oldValue, newValue);
            }
        }
    })
    /**
     * 自定义元素的构造函数
     */
    constructor() {
        // 必须首先调用 super 方法
        super();
        // 创建一个 Shadow DOM
        this.attachShadow({ mode: "open" });
        this.eventListeners = []; // 事件存储器
        this.obServerAttr.observe(this, {
            attributes: true, // 观察属性变化
            attributeOldValue: true, // 记录属性变化前的旧值
        });
    }

    /**
     * 定义响应式
     * 
     * @param {object} vars - 组件状态数据
     * @returns {Proxy} - 响应式代理对象
     */
    reactive(vars) {
        const then = this;
        return new Proxy(vars, {
            get(target, key, rec) {
                const vars = Reflect.get(target, key, rec);
                if (typeof vars === 'object' && vars !== null) {
                    return then.reactive(vars);
                }
                return vars;
            },
            set(target, key, val, rec) {
                Reflect.set(target, key, val, rec);
                then.updateComponent();
                return true;
            }
        })
    }

    /**
     * 定义响应式引用
     * 
     * @param {*} vars - 组件状态数据
     * @returns {Proxy} - 响应式引用对象
     */
    ref(vars) {
        const then = this;
        return {
            get value() {
                return vars;
            },
            set value(newVal) {
                if (newVal !== vars) {
                    vars = newVal;
                    then.updateComponent();
                }
            }
        }
    }

    /**
     * 执行组件更新（虚拟DOM对比）
     * 
     * @returns {void}
     */
    updateComponent() {
        const newTemplate = this.render();
        this.#_processTemplate(newTemplate);
    }

    /**
      * 处理模板字符串（包含事件绑定）
      * 
      * @param {string} template - 模板字符串
      * @returns {void}
      */
    #_processTemplate(template) {
        // 模板解析
        this.#diff(this.shadowRoot.childNodes, parseHTML(template));
        this.#_processEvent();// 处理事件绑定
    }

    /**
     * 处理事件绑定
     * 
     * @returns {void}
     */
    #_processEvent() {
        // 事件绑定处理
        let eventHandlers = this.shadowRoot.querySelectorAll('[data-event]');
        eventHandlers.forEach((element) => {
            const data = element.getAttribute('data-event').split(",");
            // 删除属性
            element.removeAttribute('data-event');
            if (this.eventListeners.find((item) =>
                item.element === element
                && item.type === data[0])) {
                return;
            }
            this.eventListeners.push({
                element: element,
                type: data[0],
                handler: data[1]
            })

            element.addEventListener(data[0], (e) => {
                if (this[data[1]]) {
                    this[data[1]].call(this, e, element);
                }
            })
        })
    }

    /**
     * 对比两个DOM节点列表的差异
     * 
     * @param {NodeListOf<ChildNode>} oldDom 旧DOM节点列表
     * @param {NodeListOf<ChildNode>} newDom 新DOM节点列表
     */
    #diff(oldDoms, newDoms) {
        for (let i = 0; i < oldDoms.length; i++) {
            const oldDom = oldDoms[i];
            const newDom = newDoms[i];
            // 节点不存在
            if (!newDom) {
                oldDom.removeChild(oldDom.children[i]);
                continue;
            }
            // 文本节点比较
            if (oldDom.nodeType === 3 || newDom.nodeType === 3) {
                if (oldDom.nodeValue !== newDom.nodeValue) {
                    oldDom.nodeValue = newDom.nodeValue;
                }
                continue;
            }
            // 标签名不同，直接替换
            if (oldDom.nodeName !== newDom.nodeName) {
                oldDom.outerHTML = newDom.outerHTML;
                continue;
            }
            // 属性比较
            const oldAttrs = oldDom.attributes;
            const newAttrs = newDom.attributes;
            if (typeof oldAttrs !== "undefined") {
                for (let j = 0; j < oldAttrs.length; j++) {
                    const oldAttr = oldAttrs[j];
                    const newAttr = newAttrs[j];
                    // 属性删除
                    if (!newAttrs[j]) {
                        oldDom.removeAttribute(oldAttr.name);
                        continue;
                    }
                    // 修改属性
                    if (oldAttr.name !== newAttr.name) {
                        oldDom.setAttribute(oldAttr.name, newAttr.value);
                    }
                    if (oldAttr.value !== newAttr.value) {
                        oldDom.setAttribute(oldAttr.name, newAttr.value);
                    }
                }
            }
            // 属性追加
            if (typeof newAttrs !== "undefined") {
                for (let j = 0; j < newAttrs.length; j++) {
                    const newAttr = newAttrs[j];
                    if (!oldAttrs[j]) {
                        oldDom.setAttribute(newAttr.name, newAttr.value);
                    }
                }
            }

            // 子节点比较
            this.#diff(oldDom.childNodes, newDom.childNodes);
        }

        // 检查新增节点
        if (newDoms.length > oldDoms.length) {
            for (let j = oldDoms.length; j < newDoms.length; j++) {
                if (oldDoms[0]) {
                    oldDoms[0].parentNode.appendChild(newDoms[j].cloneNode(true));
                } else {
                    this.shadowRoot.appendChild(newDoms[j].cloneNode(true));
                }
            }
        }
    }

    /**
     * 转义HTML特殊字符
     * 
     * @param {any} input - 输入字符串
     * @param {boolean} is - 是否转义
     * 
     * @returns {string} 转义后的字符串
     */
    kmHtml(input, is = true) {
        // 处理空值和函数
        if (input == null) return '';
        if (typeof input === 'function') return this.kmHtml(input());
        // 处理数组
        if (Array.isArray(input)) {
            const len = input.length;
            // 预初始化数组避免push开销
            const escaped = new Array(len);
            for (let i = 0; i < len; i++) {
                escaped[i] = this.kmHtml(input[i]);
            }
            return escaped.join(',');
        }
        // 转换非字符串为字符串
        const str = String(input);
        if (!is) return str;
        // 转义HTML特殊字符
        const escapeMap = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        const escapePattern = /[&<>"']/g;
        // 快速检查是否需要转义
        if (!escapePattern.test(str)) return str;
        return str.replace(escapePattern, char => escapeMap[char]);
    }

    css() { return ''; } // 定义样式
    render() { return ''; } // 渲染模板
    created() { } // 自定义元素添加至页面时调用
    adopted() { } // 自定义元素移动至新页面时调用
    cleaned() { } // 自定义元素从页面中移除时调用
    attred(name, oldValue, newValue) { } // 自定义元素的属性变更时调用
    /**
     * 自定义元素添加至页面时调用
     * 
     * @returns {void}
     */
    connectedCallback() {
        this.updateComponent();
        // 渲染样式
        let sheet = new CSSStyleSheet();
        sheet.replaceSync(this.css());
        this.shadowRoot.adoptedStyleSheets = [sheet];
        this.created();
    }
    adoptedCallback() {
        this.adopted();
    } // 自定义元素移动至新页面时调用
    disconnectedCallback() {
        this.cleaned();
    } // 自定义元素从页面中移除时调用
}

/**
 * 解析HTML字符串为DOM节点列表
 * 
 * @param {string} htmlString HTML字符串
 * 
 * @returns {NodeListOf<ChildNode>} DOM节点列表
 */
function parseHTML(htmlString) {
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = htmlString;
    return tempDiv.childNodes;
}

/**
 * 事件派发
 */
export class Event {

    /**
     * 初始化
     * @param {HTMLElement} component 组件实例
     */
    constructor(component) {
        this.component = component;
        this.Components = new Map();
        this.eventListeners = new Map();
    }

    /**
     * 注册组件事件
     * 
     * @param {string} name - 组件名称（标识）
     * @param {string|HTMLElement} selectorOrElement - 选择器或元素实例
     * 
     * @returns {boolean} 是否注册成功
     */
    register(name, selectorOrElement) {
        const element = typeof selectorOrElement === 'string'
            ? (this.component.shadowRoot || this.component).querySelector(selectorOrElement)
            : selectorOrElement;
        if (!element) return false;
        this.Components.set(name, element);
        return true;
    }

    /**
     * 调用组件方法
     * 
     * @param {string} name - 组件名称
     * @param {string} methodName - 方法名称
     * @param {...any} args - 方法参数
     * 
     * @returns {any} 方法返回值
     */
    call(name, methodName, ...args) {
        const child = this.Components.get(name);
        if (!child) {
            console.warn(`Component "${name}" not registered`);
            return null;
        };
        const fn = child[methodName];
        if (typeof fn !== 'function') {
            console.warn(`Method "${methodName}" not found in "${name}"`);
            return null;
        }
        try {
            return fn.apply(child, args);
        } catch (error) {
            console.error(`Error calling "${methodName}" on "${name}":`, error);
        }
        return null;
    }

    /**
     * 监听子组件事件
     * 
     * @param {string} name - 组件名称
     * @param {string} eventName - 事件名称
     * @param {Function} handler - 事件处理函数
     * 
     * @returns {void}
     */
    on(name, eventName, handler) {
        const child = this.Components.get(name);
        if (!child) return console.warn(`listen event failed, component "${name}" not register`);
        // 存储事件监听器以便后续移除
        const key = `${name}-${eventName}`;
        this.eventListeners.set(key, { child, eventName, handler });
        child.addEventListener(eventName, handler);
    }

    /**
     * 移除组件事件监听
     * @param {string} name - 组件名称
     * @param {string} eventName - 事件名称
     */
    off(name, eventName) {
        const key = `${name}-${eventName}`;
        const listener = this.eventListeners.get(key);
        if (listener) {
            listener.child.removeEventListener(listener.eventName, listener.handler);
            this.eventListeners.delete(key);
        }
    }

    /**
     * 向父组件发送事件
     * @param {string} eventName - 事件名称
     * @param {any} data - 要传递的数据
     */
    emit(eventName, data) {
        this.component.dispatchEvent(new CustomEvent(eventName, {
            detail: data,
            bubbles: true,
            composed: true
        }));
    }

    /**
     * 清理所有事件监听和注册
     */
    destroy() {
        // 移除所有事件监听
        this.eventListeners.forEach(({ child, eventName, handler }) => {
            child.removeEventListener(eventName, handler);
        });
        // 清空存储
        this.Components.clear();
        this.eventListeners.clear();
    }
}


// 判断是否是浏览器环境
if (typeof window !== 'undefined') {
    // 浏览器环境，挂载到window
    window.KMin = KMin;
    window.regComp = regComp;
    window.impComp = impComp;
    window.Event = Event;
}