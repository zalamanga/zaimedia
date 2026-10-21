import { reactive } from 'vue';

let nextId = 1;

export const toast = reactive({
  items: [],
  show(message, type = 'success', timeout = 3200) {
    const id = nextId++;
    this.items.push({ id, message, type });
    window.setTimeout(() => this.remove(id), timeout);
  },
  success(message) {
    this.show(message, 'success');
  },
  error(message) {
    this.show(message, 'error', 4400);
  },
  info(message) {
    this.show(message, 'info');
  },
  remove(id) {
    this.items = this.items.filter((item) => item.id !== id);
  },
});
