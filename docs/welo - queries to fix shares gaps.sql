# tasks closed
select *
from tasks
where stream_id = '1a12130d-b219-4a70-bbc9-cc27f16ed709'
and status = 50
;

#id primo task: 268afa8b-7e00-43bd-94a8-d3c4523102da
# shares assegnati
select *
from shares
where task_id = '268afa8b-7e00-43bd-94a8-d3c4523102da'
order by valued_id
;

#id primo task: 268afa8b-7e00-43bd-94a8-d3c4523102da
# media degli share assegnati
select *, avg(value)
from shares
where task_id = '268afa8b-7e00-43bd-94a8-d3c4523102da'
group by valued_id
order by valued_id
;

# share realmente assegnati
select *
from task_members
where 
#	task_id = '268afa8b-7e00-43bd-94a8-d3c4523102da'
	task_id = '8bc5f780-006b-4b60-a505-3b6ef2ffa3d2' # DA RIVEDERE - già modificato a mano?
order by member_id
;

#id primo task: 268afa8b-7e00-43bd-94a8-d3c4523102da
# media degli share assegnati
# select tm.*, s.*, avg(s.value)
select tm.task_id, u.firstname, u.lastname, tm.role, tm.share as saved_share, round(avg(s.value),3) as calculated_share, 
	(
	select value
	from shares sh
	where sh.task_id = tm.task_id
	and sh.valued_id = sh.evaluator_id
	and sh.valued_id = tm.member_id
	) as self_share,
	tm.delta as saved_delta,
	round(-tm.share+(
	select value
	from shares sh
	where sh.task_id = tm.task_id
	and sh.valued_id = sh.evaluator_id
	and sh.valued_id = tm.member_id
	),4) as calculated_delta
from task_members tm
join shares s 
	on tm.task_id = s.task_id
	and tm.member_id = s.valued_id
join users u
	on tm.member_id = u.id
where 
#	tm.task_id = '268afa8b-7e00-43bd-94a8-d3c4523102da'
#	tm.task_id = '2df75f04-6300-4806-a493-6efaf85f7f7a'
#	tm.task_id = '41942720-ae60-4213-8c16-26e1961fca7e'
#	tm.task_id = '4942ddc7-ee18-4383-b86d-7f041fba347f'
#	tm.task_id = '5714c82d-ca27-490e-93fb-8d2e50b245ee'
#	tm.task_id = '60349232-69de-45f1-b777-2ebc2acbb83e' # DA RIVEDERE
#	tm.task_id = '8761ce52-3f6f-4d56-a0dc-26db276a24f7'
#	tm.task_id = '8bb6fba1-2d94-498e-b897-1a3872756b4c'
#	tm.task_id = '8bc5f780-006b-4b60-a505-3b6ef2ffa3d2' # DA RIVEDERE - già modificato a mano?
#	tm.task_id = 'a63e14a8-d874-495c-843c-1f75d9eb9c2b'
#	tm.task_id = 'b58c2ed5-7516-4890-a2e7-3889a6000f80'
#	tm.task_id = '25a5aaf3-6193-458a-81a6-3d263d42c646'
	tm.task_id = '9dfbbae0-76f6-49e8-9c8f-9e7cfc0b5b48'
group by valued_id
order by valued_id
;



# update dei delta assegnati

update task_members tm
set
	tm.delta =
	round(-tm.share+(
	select value
	from shares sh
	where sh.task_id = tm.task_id
	and sh.valued_id = sh.evaluator_id
	and sh.valued_id = tm.member_id
	),4)
where 
	tm.task_id in (
		select id
		from tasks
		where stream_id in (
		#	'25a5aaf3-6193-458a-81a6-3d263d42c646', # cocoon pro
			'16b46c5b-82e7-47d4-b372-1b658e89751d', # pdtoolkit
			'68f53989-bb23-46ed-9632-a1ad7370eb3d'  # startupme
		)
		and status = 50
	)

;

