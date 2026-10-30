(() => {
  'use strict';
  if (!document.querySelector('.staff-shell')) return;
  const numeric = /^(?:total|price|stock|quantity|unit price|products|attempts|orders placed|failed attempts)$/i;
  function enhance() {
    const root=document.querySelector('.staff-main'); if (!root) return;
    root.querySelectorAll('.data-table,.table-wrap,.order-table-wrap,.catalog-activity-table').forEach(wrapper=>{if(!wrapper.hasAttribute('tabindex'))wrapper.tabIndex=0;});
    root.querySelectorAll('table').forEach(table => {
      const heads=[...table.querySelectorAll('thead th')];
      heads.forEach((head,index) => { if (!numeric.test(head.textContent.trim())) return; head.classList.add('num'); table.querySelectorAll('tbody tr').forEach(row => { if(row.cells.length===heads.length) row.cells[index]?.classList.add('num'); }); });
    });
    // Rows remain ordinary links/forms. Feedback never changes or assumes a saved value.
    root.querySelectorAll('details.staff-help,details.staff-log-filters').forEach(details => {
      if(details.dataset.staffMotion)return;details.dataset.staffMotion='true';
      details.addEventListener('toggle',()=>{if(details.open)window.AvelaMotion?.reveal(details,'fast');});
    });
    root.querySelectorAll('[data-staff-period]').forEach(form => {
      if(form.dataset.staffAuto)return;form.dataset.staffAuto='true';
      form.querySelector('select')?.addEventListener('change',()=>form.requestSubmit());
      const button=form.querySelector('[data-period-submit]');if(button)button.hidden=true;
    });
  }
  document.readyState==='loading'?document.addEventListener('DOMContentLoaded',enhance,{once:true}):enhance();
  document.addEventListener('avela:content',enhance);
})();
