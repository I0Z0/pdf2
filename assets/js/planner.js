let currentDate = new Date();
let selectedDate = new Date();

function renderCalendar() {
  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();
  document.getElementById('monthLabel').textContent = currentDate.toLocaleString('lv-LV', {month:'long', year:'numeric'});
  const cal = document.getElementById('calendar');
  cal.innerHTML = '';
  const grid = document.createElement('div');
  grid.className = 'calendar-grid';
  const firstDay = new Date(year, month, 1).getDay();
  const offset = (firstDay + 6) % 7; // Monday first
  for (let i=0;i<offset;i++) {
    const cell = document.createElement('div');
    cell.className = 'calendar-cell empty';
    grid.appendChild(cell);
  }
  const daysInMonth = new Date(year, month+1, 0).getDate();
  for (let d=1; d<=daysInMonth; d++) {
    const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const cell = document.createElement('div');
    cell.className = 'calendar-cell';
    cell.dataset.date = dateStr;
    const num = document.createElement('div');
    num.className = 'day-number';
    num.textContent = d;
    cell.appendChild(num);
    const dots = document.createElement('div');
    dots.className = 'dots';
      plannerEvents.filter(e=>dateStr>=e.date && dateStr<=(e.end_date||e.date)).forEach(e=>{
      const dot = document.createElement('span');
      dot.style.backgroundColor = e.color;
      dots.appendChild(dot);
    });
    cell.appendChild(dots);
    cell.addEventListener('click', () => {
      selectedDate = new Date(dateStr);
      renderDay();
    });
    grid.appendChild(cell);
  }
  cal.appendChild(grid);
}

function renderDay() {
  const label = document.getElementById('dayLabel');
  const container = document.getElementById('dayEvents');
  const dateStr = selectedDate.toISOString().slice(0,10);
  label.textContent = dateStr;
    const list = plannerEvents.filter(e=>dateStr>=e.date && dateStr<=(e.end_date||e.date)).sort((a,b)=>{
    return (a.start||'').localeCompare(b.start||'');
  });
  container.innerHTML = '';
  if (list.length === 0) {
    container.textContent = 'Šai dienai nav notikumu';
    return;
  }
  list.forEach(e=>{
    const div = document.createElement('div');
    div.className = 'event';
    div.style.borderColor = e.color;
    const time = (e.start?e.start:'') + (e.end?'-'+e.end:'');
    const span = document.createElement('span');
    span.textContent = (time?`[${time}] `:'') + e.title;
    div.appendChild(span);
    const btn = document.createElement('button');
    btn.textContent = '×';
    btn.addEventListener('click', ()=>deleteEvent(e.id));
    div.appendChild(btn);
    container.appendChild(div);
  });
}

function deleteEvent(id){
    fetch('includes/process_planner.php', {
    method: 'POST',
    body: new URLSearchParams({action:'deleteEvent', id})
  }).then(r=>r.json()).then(res=>{
    if(res.success){
      plannerEvents = plannerEvents.filter(e=>e.id!==id);
      renderCalendar();
      renderDay();
    }
  });
}

document.getElementById('prevMonth').addEventListener('click', ()=>{
  currentDate.setMonth(currentDate.getMonth()-1);
  renderCalendar();
});
document.getElementById('nextMonth').addEventListener('click', ()=>{
  currentDate.setMonth(currentDate.getMonth()+1);
  renderCalendar();
});

document.getElementById('eventForm').addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('action','addEvent');
  fetch('includes/process_planner.php', {
    method: 'POST',
    body: new URLSearchParams(fd)
  }).then(r=>r.json()).then(res=>{
    if(res.success){
        plannerEvents.push(res.event);
        this.reset();
        currentDate = new Date(res.event.date);
        selectedDate = new Date(res.event.date);
        renderCalendar();
        renderDay();
        document.getElementById('eventModal').classList.add('hidden');
      }
    });
  });

function renderNotes(){
  const list = document.getElementById('notesList');
  list.innerHTML = '';
  plannerNotes.forEach(n=>{
    const li = document.createElement('li');
    li.style.backgroundColor = n.color;
    li.textContent = n.text;
    const btn = document.createElement('button');
    btn.textContent = '×';
    btn.addEventListener('click', ()=>deleteNote(n.id));
    li.appendChild(btn);
    list.appendChild(li);
  });
}

function deleteNote(id){
  fetch('includes/process_planner.php', {
    method:'POST',
    body:new URLSearchParams({action:'deleteNote', id})
  }).then(r=>r.json()).then(res=>{
    if(res.success){
      plannerNotes = plannerNotes.filter(n=>n.id!==id);
      renderNotes();
    }
  });
}

document.getElementById('noteForm').addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('action','addNote');
  fetch('includes/process_planner.php', {
    method:'POST',
    body:new URLSearchParams(fd)
  }).then(r=>r.json()).then(res=>{
    if(res.success){
      plannerNotes.push(res.note);
      this.reset();
      renderNotes();
    }
  });
});

function bindPayButtons(){
  document.querySelectorAll('#unpaidInvoices .pay-btn').forEach(btn=>{
    btn.addEventListener('click', function(){
      const tr = this.closest('tr');
      const inv = tr.dataset.inv;
      fetch('includes/process_planner.php', {
        method:'POST',
        body:new URLSearchParams({action:'payInvoiceNo', invoice_no: inv})
      }).then(r=>r.json()).then(res=>{
        if(res.success){
          tr.remove();
        }
      });
    });
  });
}

renderCalendar();
renderDay();
renderNotes();
bindPayButtons();

const modal = document.getElementById('eventModal');
document.getElementById('addEventBtn').addEventListener('click', ()=>{
  modal.classList.remove('hidden');
});
modal.querySelector('.close').addEventListener('click', ()=>{
  modal.classList.add('hidden');
});
